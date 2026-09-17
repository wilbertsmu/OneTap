package com.smu.onetap

import android.app.PendingIntent
import android.content.Intent
import android.media.AudioManager
import android.media.ToneGenerator
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
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var repository: ScanRepository
    private lateinit var prefs: Prefs
    private var nfcAdapter: NfcAdapter? = null
    private var toneGenerator: ToneGenerator? = null

    /** UID -> when it was last accepted (not just last attempted) as a scan. */
    private val recentScanTimestamps = mutableMapOf<String, Long>()

    /** Clears the result off the screen back to "Ready" a few seconds after it's shown. */
    private var clearResultJob: Job? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        prefs = Prefs(this)
        repository = ScanRepository(this)
        nfcAdapter = NfcAdapter.getDefaultAdapter(this)
        toneGenerator = ToneGenerator(AudioManager.STREAM_NOTIFICATION, DUPLICATE_BEEP_VOLUME)

        binding.settingsButton.setOnClickListener { showSettingsDialog() }
        binding.historyButton.setOnClickListener {
            startActivity(Intent(this, HistoryActivity::class.java))
        }

        refreshPendingCount()
    }

    override fun onDestroy() {
        super.onDestroy()
        toneGenerator?.release()
        toneGenerator = null
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
        clearResultJob?.cancel()

        val now = System.currentTimeMillis()
        val lastAccepted = recentScanTimestamps[uid]
        val secondsSinceLast = lastAccepted?.let { (now - it) / 1000 }

        // Fires the instant the tag is read, independent of the network
        // round-trip — the immediate, zero-latency duplicate cue.
        if (lastAccepted != null && now - lastAccepted < DUPLICATE_COOLDOWN_MS) {
            toneGenerator?.startTone(ToneGenerator.TONE_PROP_BEEP, DUPLICATE_BEEP_DURATION_MS)
            binding.resultIcon.text = "⏱️"
            binding.statusText.text = "Same card tapped ${secondsSinceLast}s ago — ignored"
            binding.idNumberText.text = ""
            binding.studentText.text = "The card has already been recorded"
            binding.courseText.isVisible = false
            binding.departmentText.text = ""
            binding.detailText.text = uid
            scheduleClear()
            return
        }

        pruneOldTimestamps(now)
        recentScanTimestamps[uid] = now

        binding.resultIcon.text = "⏳"
        binding.statusText.text = "Card detected — looking up…"
        binding.idNumberText.text = ""
        binding.studentText.text = ""
        binding.courseText.isVisible = false
        binding.departmentText.text = ""
        binding.detailText.text = uid

        lifecycleScope.launch {
            val outcome = repository.submitScan(uid)
            renderOutcome(outcome)
            refreshPendingCount()
        }
    }

    /** Blanks the result and returns the screen to "Ready", a few seconds after showing it. */
    private fun scheduleClear() {
        clearResultJob = lifecycleScope.launch {
            delay(RESULT_DISPLAY_MS)
            binding.resultIcon.text = "📶"
            binding.statusText.text = "Ready"
            binding.idNumberText.text = ""
            binding.studentText.text = ""
            binding.courseText.text = ""
            binding.courseText.isVisible = false
            binding.departmentText.text = ""
            binding.detailText.text = ""
        }
    }

    private fun pruneOldTimestamps(now: Long) {
        recentScanTimestamps.entries.removeAll { now - it.value >= DUPLICATE_COOLDOWN_MS }
    }

    private fun renderOutcome(outcome: ScanOutcome) {
        when (outcome) {
            is ScanOutcome.Online -> {
                val response = outcome.response
                when (response.result) {
                    "success" -> {
                        val holder = response.student
                        val isStudent = !holder?.course.isNullOrBlank()

                        binding.resultIcon.text = "✅"
                        binding.statusText.text = "Ready"
                        binding.idNumberText.text = holder?.idNumber ?: ""
                        binding.studentText.text = holder?.fullName ?: ""
                        if (isStudent) {
                            binding.courseText.text = holder?.course ?: ""
                            binding.courseText.isVisible = true
                        } else {
                            binding.courseText.text = ""
                            binding.courseText.isVisible = false
                        }
                        binding.departmentText.text = holder?.department ?: ""
                        binding.detailText.text = ""
                        scheduleClear()
                    }
                    else -> {
                        binding.resultIcon.text = "⛔"
                        binding.statusText.text = response.message
                        binding.idNumberText.text = ""
                        binding.studentText.text = response.student?.fullName ?: ""
                        binding.courseText.isVisible = false
                        binding.departmentText.text = ""
                        binding.detailText.text = ""
                    }
                }
            }
            is ScanOutcome.Queued -> {
                binding.resultIcon.text = "📴"
                binding.statusText.text = "No connection — scan saved"
                binding.idNumberText.text = ""
                binding.studentText.text = "Will sync automatically"
                binding.courseText.isVisible = false
                binding.departmentText.text = ""
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

    companion object {
        private const val DUPLICATE_BEEP_DURATION_MS = 150
        private const val DUPLICATE_BEEP_VOLUME = 80 // 0-100
        private const val DUPLICATE_COOLDOWN_MS = 60_000L
        private const val RESULT_DISPLAY_MS = 5_000L
    }
}
