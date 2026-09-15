package com.smu.onetap

import android.content.Context
import android.content.SharedPreferences

/**
 * Runtime-configurable settings so the same APK can be pointed at different
 * servers/tokens per device without a rebuild.
 */
class Prefs(context: Context) {

    private val sp: SharedPreferences =
        context.getSharedPreferences("onetap_prefs", Context.MODE_PRIVATE)

    var baseUrl: String
        get() = sp.getString(KEY_BASE_URL, DEFAULT_BASE_URL) ?: DEFAULT_BASE_URL
        set(value) = sp.edit().putString(KEY_BASE_URL, value).apply()

    var apiToken: String
        get() = sp.getString(KEY_TOKEN, "") ?: ""
        set(value) = sp.edit().putString(KEY_TOKEN, value).apply()

    var deviceId: String
        get() = sp.getString(KEY_DEVICE_ID, DEFAULT_DEVICE_ID) ?: DEFAULT_DEVICE_ID
        set(value) = sp.edit().putString(KEY_DEVICE_ID, value).apply()

    companion object {
        private const val KEY_BASE_URL = "base_url"
        private const val KEY_TOKEN = "api_token"
        private const val KEY_DEVICE_ID = "device_id"

        const val DEFAULT_BASE_URL = "http://103.148.246.91:8003/"
        const val DEFAULT_DEVICE_ID = "unconfigured-device"
    }
}
