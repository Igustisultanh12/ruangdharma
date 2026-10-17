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
private val DARK_KEY = stringPreferencesKey("theme_dark") // "1" | "0" | (kosong = ikut sistem)

object TokenStore {
    fun tokenFlow(context: Context): Flow<String?> =
        context.dataStore.data.map { it[TOKEN_KEY] }
    suspend fun save(context: Context, token: String?) {
        context.dataStore.edit { if (token == null) it.remove(TOKEN_KEY) else it[TOKEN_KEY] = token }
    }
    fun darkFlow(context: Context): Flow<Boolean?> =
        context.dataStore.data.map { p -> p[DARK_KEY]?.let { it == "1" } }
    suspend fun saveDark(context: Context, dark: Boolean) {
        context.dataStore.edit { it[DARK_KEY] = if (dark) "1" else "0" }
    }
}

object RetrofitClient {
    const val BASE_URL = "https://ruangdharma.my.id/api/"

    /**
     * @param onUnauthorized dipanggil bila server menjawab 401 untuk request ber-token
     *        (token dicabut / kedaluwarsa) → app kembali ke layar login.
     */
    fun create(context: Context, onUnauthorized: () -> Unit = {}, tokenProvider: () -> String?): ApiService {
        val auth = Interceptor { chain ->
            val token = tokenProvider()
            val req = chain.request().newBuilder().apply {
                token?.let { addHeader("Authorization", "Bearer " + it) }
                addHeader("Accept", "application/json")
                addHeader("X-Requested-With", "XMLHttpRequest")
            }.build()
            val res = chain.proceed(req)
            if (res.code == 401 && token != null && !req.url.encodedPath.endsWith("/login")) onUnauthorized()
            res
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
