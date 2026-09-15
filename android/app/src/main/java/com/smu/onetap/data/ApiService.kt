package com.smu.onetap.data

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.POST

interface ApiService {
    @POST("api/scans")
    suspend fun postScan(@Body request: ScanRequest): Response<ScanResponse>
}
