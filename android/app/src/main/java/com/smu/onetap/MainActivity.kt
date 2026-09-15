package com.smu.onetap

import android.app.PendingIntent
import android.content.Intent
import android.nfc.NfcAdapter
import android.nfc.Tag
import android.os.Bundle
import android.view.LayoutInflater
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.isVisible
import androidx.lifecycle.lifecycleScope
import com.smu.onetap.data.ScanOutcome
import com.smu.onetap.data.ScanRepository
import com.smu.onetap.databinding.ActivityMainBinding
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var repository: ScanRepository
    private lateinit var prefs: Prefs
    private var nfcAdapter: NfcAdapter? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        prefs = Prefs(this)
        repository = ScanRepository(this)
        nfcAdapter = NfcAdapter.getDefaultAdapter(this)

        binding.settingsButton.setOnClickListener { showSettingsDialog() }

        refreshPendingCount()
    }

    override fun onResume() {
        super.onResume()

        val adapter = nfcAdapter
        if (adapter == null) {
            binding.statusText.text = "This device has no NFC hardware"
            return
        }
        if (!adapter.isEnabled) {
            binding.statusText.text = "NFC is turned off — enable it in system settings"
            return
        }

        val intent = Intent(this, javaClass).apply {
            addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent, PendingIntent.FLAG_MUTABLE
        )
        val techLists = arrayOf(
            arrayOf(android.nfc.tech.NfcA::class.java.name),
            arrayOf(android.nfc.tech.NfcB::class.java.name),
            arrayOf(android.nfc.tech.NfcF::class.java.name),
            arrayOf(android.nfc.tech.NfcV::class.java.name),
            arrayOf(android.nfc.tech.IsoDep::class.java.name),
            arrayOf(android.nfc.tech.MifareClassic::class.java.name),
            arrayOf(android.nfc.tech.MifareUltralight::class.java.name)
        )
        adapter.enableForegroundDispatch(this, pendingIntent, null, techLists)

        if (prefs.apiToken.isBlank()) {
            binding.statusText.text = "Set the API token in Settings before scanning"
        } else {
            binding.statusText.text = "Tap a card to begin"
        }
    }

    override fun onPause() {
        super.onPause()
        nfcAdapter?.disableForegroundDispatch(this)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        val tag: Tag? = if (android.os.Build.VERSION.SDK_INT >= 33) {
            intent.getParcelableExtra(NfcAdapter.EXTRA_TAG, Tag::class.java)
        } else {
            @Suppress("DEPRECATION")
            intent.getParcelableExtra(NfcAdapter.EXTRA_TAG)
        }
        val uid = tag?.id?.let { toDecimalUid(it) } ?: return
        handleScan(uid)
    }

    /**
     * Converts a tag's raw UID bytes to the decimal format used by the
     * existing card database (e.g. "1298436754"), matching how common
     * access-control systems represent a little-endian 4-byte card UID.
     */
    private fun toDecimalUid(bytes: ByteArray): String {
        val littleEndian = bytes.reversedArray()
        return java.math.BigInteger(1, littleEndian).toString()
    }

    private fun handleScan(uid: String) {
        binding.resultIcon.text = "⏳"
        binding.statusText.text = "Reading card…"
        binding.studentText.text = ""
        binding.detailText.text = uid

        lifecycleScope.launch {
            val outcome = repository.submitScan(uid)
            renderOutcome(outcome)
            refreshPendingCount()
        }
    }

    private fun renderOutcome(outcome: ScanOutcome) {
        when (outcome) {
            is ScanOutcome.Online -> {
                val response = outcome.response
                when (response.result) {
                    "success" -> {
                        binding.resultIcon.text = "✅"
                        binding.statusText.text = "Welcome"
                        binding.studentText.text = response.student?.fullName ?: ""
                        binding.detailText.text = listOfNotNull(
                            response.student?.idNumber,
                            response.student?.course,
                            response.student?.yearLevel
                        ).joinToString(" • ")
                    }
                    else -> {
                        binding.resultIcon.text = "⛔"
                        binding.statusText.text = response.message
                        binding.studentText.text = response.student?.fullName ?: ""
                        binding.detailText.text = ""
                    }
                }
            }
            is ScanOutcome.Queued -> {
                binding.resultIcon.text = "📴"
                binding.statusText.text = "No connection — scan saved"
                binding.studentText.text = "Will sync automatically"
                binding.detailText.text = ""
            }
        }
    }

    private fun refreshPendingCount() {
        lifecycleScope.launch {
            val count = repository.pendingCount()
            binding.pendingText.isVisible = count > 0
            binding.pendingText.text = if (count > 0) {
                "$count scan(s) waiting to sync"
            } else {
                ""
            }
            if (count > 0) {
                repository.scheduleSync()
            }
        }
    }

    private fun showSettingsDialog() {
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_settings, null)
        val urlField = view.findViewById<EditText>(R.id.baseUrlField)
        val tokenField = view.findViewById<EditText>(R.id.tokenField)
        val deviceField = view.findViewById<EditText>(R.id.deviceIdField)

        urlField.setText(prefs.baseUrl)
        tokenField.setText(prefs.apiToken)
        deviceField.setText(prefs.deviceId)

        AlertDialog.Builder(this)
            .setTitle("Device Settings")
            .setView(view)
            .setPositiveButton("Save") { _, _ ->
                prefs.baseUrl = urlField.text.toString().trim()
                prefs.apiToken = tokenField.text.toString().trim()
                prefs.deviceId = deviceField.text.toString().trim()
                Toast.makeText(this, "Settings saved", Toast.LENGTH_SHORT).show()
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
}
