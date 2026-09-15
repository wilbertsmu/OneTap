package com.smu.onetap.data

import android.content.Context
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import com.smu.onetap.Prefs
import com.smu.onetap.work.SyncWorker
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone

sealed class ScanOutcome {
    data class Online(val response: ScanResponse) : ScanOutcome()
    data class Queued(val pendingCount: Int) : ScanOutcome()
}

class ScanRepository(private val context: Context) {

    private val prefs = Prefs(context)
    private val dao = AppDatabase.get(context).pendingScanDao()

    suspend fun submitScan(uid: String): ScanOutcome {
        val nowIso = isoNow()
        val api = ApiClient.create(prefs)

        return try {
            val response = api.postScan(
                ScanRequest(uid = uid, deviceId = prefs.deviceId, scannedAt = nowIso)
            )
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ScanOutcome.Online(body)
            } else {
                queue(uid, nowIso)
            }
        } catch (e: Exception) {
            queue(uid, nowIso)
        }
    }

    private suspend fun queue(uid: String, scannedAtIso: String): ScanOutcome.Queued {
        dao.insert(PendingScan(uid = uid, deviceId = prefs.deviceId, scannedAtIso = scannedAtIso))
        scheduleSync()
        return ScanOutcome.Queued(dao.count())
    }

    suspend fun pendingCount(): Int = dao.count()

    fun scheduleSync() {
        val request = OneTimeWorkRequestBuilder<SyncWorker>().build()
        WorkManager.getInstance(context)
            .enqueueUniqueWork("sync_pending_scans", ExistingWorkPolicy.KEEP, request)
    }

    companion object {
        fun isoNow(): String {
            val fmt = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US)
            fmt.timeZone = TimeZone.getDefault()
            return fmt.format(Date())
        }
    }
}
