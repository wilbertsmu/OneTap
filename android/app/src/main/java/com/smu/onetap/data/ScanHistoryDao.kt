package com.smu.onetap.data

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.Query

@Dao
interface ScanHistoryDao {
    @Insert
    suspend fun insert(entry: ScanHistoryEntry): Long

    @Query("SELECT * FROM scan_history ORDER BY scannedAtMillis DESC LIMIT :limit")
    suspend fun getRecent(limit: Int = 200): List<ScanHistoryEntry>

    @Query("DELETE FROM scan_history WHERE id NOT IN (SELECT id FROM scan_history ORDER BY scannedAtMillis DESC LIMIT :keep)")
    suspend fun trimTo(keep: Int)

    @Query("""
        UPDATE scan_history
        SET holderName = :holderName, idNumber = :idNumber, result = :result, synced = 1
        WHERE id = :id
    """)
    suspend fun markSynced(id: Long, holderName: String?, idNumber: String?, result: String)
}
