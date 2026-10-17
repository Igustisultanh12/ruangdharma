package id.ruangdharma.studio.data.api

import id.ruangdharma.studio.data.model.*
import com.google.gson.JsonObject
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.*

/* Bentuk respons disamakan dengan controller Laravel (lihat app/Http/Controllers). */
interface ApiService {
    // {success, message, token, user, data:{token,user}}
    @POST("login")
    suspend fun login(@Body body: Map<String, String>): Response<LoginResponse>

    @POST("logout")
    suspend fun logout(): Response<JsonObject>

    // {success, user}
    @GET("me")
    suspend fun me(): Response<MeResponse>

    @GET("realtime/pulse")
    suspend fun pulse(): Response<Pulse>

    @GET("generations")
    suspend fun generations(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 30,
        @Query("favorites") favorites: String? = null,
        @Query("archived") archived: String? = "false",
        @Query("search") search: String? = null,
    ): Response<ApiWrapper<Paginator<Generation>>>

    @GET("generations/counts")
    suspend fun counts(): Response<ApiWrapper<Counts>>

    @GET("generations/{id}")
    suspend fun generation(@Path("id") id: Int): Response<ApiWrapper<Generation>>

    // {success, message, generation, ...}
    @POST("generations")
    suspend fun createGeneration(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<JsonObject>

    @POST("generations/{id}/toggle-favorite")
    suspend fun toggleFavorite(@Path("id") id: Int): Response<ApiWrapper<Generation>>

    @PUT("generations/{id}/backsound")
    suspend fun updateBacksound(@Path("id") id: Int, @Body body: Map<String, @JvmSuppressWildcards Any?>): Response<JsonObject>

    @GET("social/stats")
    suspend fun socialStats(@Query("platform") platform: String): Response<StatsResponse>

    @GET("social/publishes")
    suspend fun socialPublishes(): Response<ApiWrapper<Paginator<SocialPublish>>>

    @POST("social/publish")
    suspend fun socialPublish(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<JsonObject>

    @POST("social/refresh")
    suspend fun socialRefresh(): Response<JsonObject>

    @GET("admin/muse-config/token-usage")
    suspend fun tokenUsage(): Response<ApiWrapper<TokenUsage>>

    @GET("backsounds")
    suspend fun backsounds(): Response<ApiWrapper<Paginator<Backsound>>>

    @Multipart
    @POST("backsounds")
    suspend fun uploadBacksound(
        @Part("name") name: RequestBody,
        @Part file: MultipartBody.Part
    ): Response<JsonObject>

    // ---------- Notifikasi ----------
    @GET("notifications")
    suspend fun notifications(@Query("per_page") perPage: Int = 30): Response<NotificationsResponse>

    @POST("notifications/{id}/read")
    suspend fun readNotification(@Path("id") id: Int): Response<JsonObject>

    @POST("notifications/read-all")
    suspend fun readAllNotifications(): Response<JsonObject>

    @GET("mobile/config")
    suspend fun mobileConfig(): Response<MobileConfig>

    @POST("push-token")
    suspend fun registerPush(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<JsonObject>

    @HTTP(method = "DELETE", path = "push-token", hasBody = true)
    suspend fun unregisterPush(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<JsonObject>

    @POST("push-test")
    suspend fun pushTest(): Response<JsonObject>
}
