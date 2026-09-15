package com.smu.onetap.data

import com.smu.onetap.Prefs
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Rebuilt on demand so a settings change (base URL / token) takes effect
 * without restarting the app.
 */
object ApiClient {

    fun create(prefs: Prefs): ApiService {
        val authInterceptor = Interceptor { chain ->
            val request = chain.request().newBuilder()
                .addHeader("Accept", "application/json")
                .apply {
                    if (prefs.apiToken.isNotBlank()) {
                        addHeader("Authorization", "Bearer ${prefs.apiToken}")
                    }
                }
                .build()
            chain.proceed(request)
        }

        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }

        val client = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(10, TimeUnit.SECONDS)
            .addInterceptor(authInterceptor)
            .addInterceptor(logging)
            .build()

        val baseUrl = if (prefs.baseUrl.endsWith("/")) prefs.baseUrl else "${prefs.baseUrl}/"

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }
}
