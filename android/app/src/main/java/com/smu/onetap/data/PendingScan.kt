package com.smu.onetap.data

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * A scan that couldn't reach the server immediately (no network / request
 * failure) and is queued for retry so a network blip never loses an entry.
 */
@Entity(tableName = "pending_scans")
data class PendingScan(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val uid: String,
    val deviceId: String?,
    val scannedAtIso: String,
    val createdAtMillis: Long = System.currentTimeMillis(),
    /** The scan_history row to update once this finally syncs. */
    val historyId: Long
)
