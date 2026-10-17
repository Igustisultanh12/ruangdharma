package id.ruangdharma.studio.data.api

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

private val Context.dataStore by preferencesDataStore("ruangdharma_prefs")
private val TOKEN_KEY = stringPreferencesKey("auth_token")

object TokenStore {
    fun tokenFlow(context: Context): Flow<String?> =
        context.dataStore.data.map { it[TOKEN_KEY] }
    suspend fun save(context: Context, token: String?) {
        context.dataStore.edit { if (token == null) it.remove(TOKEN_KEY) else it[TOKEN_KEY] = token }
    }
}

object RetrofitClient {
    const val BASE_URL = "https://ruangdharma.my.id/api/"

    fun create(context: Context, tokenProvider: () -> String?): ApiService {
        val auth = Interceptor { chain ->
            val req = chain.request().newBuilder().apply {
                tokenProvider()?.let { addHeader("Authorization", "Bearer " + it) }
                addHeader("Accept", "application/json")
            }.build()
            chain.proceed(req)
        }
        val logging = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }
        val client = OkHttpClient.Builder()
            .addInterceptor(auth).addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS).readTimeout(60, TimeUnit.SECONDS)
            .build()
        return Retrofit.Builder().baseUrl(BASE_URL).client(client)
            .addConverterFactory(GsonConverterFactory.create()).build()
            .create(ApiService::class.java)
    }
}
