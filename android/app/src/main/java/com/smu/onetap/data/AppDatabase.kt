package com.smu.onetap.data

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(
    entities = [PendingScan::class, ScanHistoryEntry::class],
    version = 2,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun pendingScanDao(): PendingScanDao
    abstract fun scanHistoryDao(): ScanHistoryDao

    companion object {
        @Volatile private var instance: AppDatabase? = null

        fun get(context: Context): AppDatabase =
            instance ?: synchronized(this) {
                instance ?: Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "onetap.db"
                )
                    // Adding scan_history in v2; any scan still mid-sync at
                    // update time would be lost, which is an acceptable
                    // tradeoff for this internal tool over hand-writing a
                    // migration for one small queue table.
                    .fallbackToDestructiveMigration()
                    .build()
                    .also { instance = it }
            }
    }
}
