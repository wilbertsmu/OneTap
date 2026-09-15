package com.smu.onetap.data

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.Query

@Dao
interface PendingScanDao {
    @Insert
    suspend fun insert(scan: PendingScan): Long

    @Query("SELECT * FROM pending_scans ORDER BY createdAtMillis ASC")
    suspend fun getAll(): List<PendingScan>

    @Query("SELECT COUNT(*) FROM pending_scans")
    suspend fun count(): Int

    @Delete
    suspend fun delete(scan: PendingScan)
}
