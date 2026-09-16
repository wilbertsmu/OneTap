package com.smu.onetap.data

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * A local record of every tap this device has made, regardless of outcome —
 * so a guard can glance back at "who was that / did that register?" without
 * needing the admin panel. Capped and trimmed in ScanRepository so this
 * doesn't grow unbounded on the phone.
 */
@Entity(tableName = "scan_history")
data class ScanHistoryEntry(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val uid: String,
    val holderName: String?,
    val idNumber: String?,
    val result: String,
    val synced: Boolean,
    val scannedAtMillis: Long = System.currentTimeMillis()
)
