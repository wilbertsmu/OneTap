package com.smu.onetap.data

import com.google.gson.annotations.SerializedName

data class ScanRequest(
    val uid: String,
    @SerializedName("device_id") val deviceId: String?,
    @SerializedName("scanned_at") val scannedAt: String?
)

data class ScanResponse(
    val result: String,
    val message: String,
    val student: StudentDto?,
    @SerializedName("logged_at") val loggedAt: String?
)

data class StudentDto(
    @SerializedName("id_number") val idNumber: String,
    @SerializedName("first_name") val firstName: String,
    @SerializedName("last_name") val lastName: String,
    @SerializedName("full_name") val fullName: String,
    val course: String,
    @SerializedName("year_level") val yearLevel: String,
    val department: String,
    val status: String
)
