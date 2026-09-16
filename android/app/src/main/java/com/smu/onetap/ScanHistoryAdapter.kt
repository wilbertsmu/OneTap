package com.smu.onetap

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.smu.onetap.data.ScanHistoryEntry
import com.smu.onetap.databinding.ItemScanHistoryBinding
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class ScanHistoryAdapter(private var items: List<ScanHistoryEntry> = emptyList()) :
    RecyclerView.Adapter<ScanHistoryAdapter.ViewHolder>() {

    fun submitList(newItems: List<ScanHistoryEntry>) {
        items = newItems
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemScanHistoryBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(items[position])
    }

    override fun getItemCount(): Int = items.size

    class ViewHolder(private val binding: ItemScanHistoryBinding) :
        RecyclerView.ViewHolder(binding.root) {

        private val timeFormat = SimpleDateFormat("MMM d, h:mm a", Locale.getDefault())

        fun bind(entry: ScanHistoryEntry) {
            binding.resultIcon.text = iconFor(entry.result)
            binding.nameText.text = entry.holderName ?: messageFor(entry.result)
            binding.detailText.text = listOfNotNull(entry.idNumber, entry.uid).joinToString(" • ")
            binding.timeText.text = timeFormat.format(Date(entry.scannedAtMillis))
        }

        private fun iconFor(result: String): String = when (result) {
            "success" -> "✅"
            "pending_sync" -> "📴"
            else -> "⛔"
        }

        private fun messageFor(result: String): String = when (result) {
            "unregistered_card" -> "Unregistered card"
            "inactive_card" -> "Inactive card"
            "inactive_student" -> "Inactive student"
            "inactive_employee" -> "Inactive employee"
            "pending_sync" -> "Waiting to sync…"
            else -> "Unknown"
        }
    }
}
