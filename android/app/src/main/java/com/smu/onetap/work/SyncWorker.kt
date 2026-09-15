package com.smu.onetap.work

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.smu.onetap.Prefs
import com.smu.onetap.data.ApiClient
import com.smu.onetap.data.AppDatabase
import com.smu.onetap.data.ScanRequest

/**
 * Flushes queued scans in order. Retries later (via WorkManager's normal
 * retry/backoff) if the network is still unavailable.
 */
class SyncWorker(
    context: Context,
    params: WorkerParameters
) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        val prefs = Prefs(applicationContext)
        val dao = AppDatabase.get(applicationContext).pendingScanDao()
        val api = ApiClient.create(prefs)

        val pending = dao.getAll()
        if (pending.isEmpty()) return Result.success()

        var allSucceeded = true

        for (scan in pending) {
            try {
                val response = api.postScan(
                    ScanRequest(uid = scan.uid, deviceId = scan.deviceId, scannedAt = scan.scannedAtIso)
                )
                if (response.isSuccessful) {
                    dao.delete(scan)
                } else {
                    allSucceeded = false
                }
            } catch (e: Exception) {
                allSucceeded = false
                // Network still down — stop here, keep the rest queued in order.
                break
            }
        }

        return if (allSucceeded) Result.success() else Result.retry()
    }
}
