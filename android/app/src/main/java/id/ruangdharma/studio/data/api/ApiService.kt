package id.ruangdharma.studio.data.api

import id.ruangdharma.studio.data.model.*
import com.google.gson.JsonObject
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    // Login: server mengirim {success, message, token, user} di level ATAS,
    // BUKAN ApiWrapper<LoginResponse> (tidak ada field "data" wajib).
    @POST("login")
    suspend fun login(@Body body: Map<String, String>): Response<LoginResponse>

    @POST("logout")
    suspend fun logout(): Response<ApiWrapper<JsonObject>>

    @GET("me")
    suspend fun me(): Response<ApiWrapper<User>>

    @GET("generations")
    suspend fun generations(@Query("page") page: Int = 1): Response<ApiWrapper<Paginator<Generation>>>

    @GET("generations/{id}")
    suspend fun generation(@Path("id") id: Int): Response<ApiWrapper<Generation>>

    @POST("generations")
    suspend fun createGeneration(@Body body: Map<String, Any?>): Response<ApiWrapper<JsonObject>>

    @PUT("generations/{id}/backsound")
    suspend fun updateBacksound(@Path("id") id: Int, @Body body: Map<String, Any?>): Response<ApiWrapper<Generation>>

    @GET("social/stats")
    suspend fun socialStats(): Response<ApiWrapper<List<SocialStats>>>

    @GET("social/publishes")
    suspend fun socialPublishes(): Response<ApiWrapper<List<SocialPublish>>>

    @POST("social/publish")
    suspend fun socialPublish(@Body body: Map<String, Any?>): Response<ApiWrapper<JsonObject>>

    @POST("social/refresh")
    suspend fun socialRefresh(): Response<ApiWrapper<JsonObject>>

    @GET("admin/muse-config/token-usage")
    suspend fun tokenUsage(): Response<ApiWrapper<TokenUsage>>

    @GET("backsounds")
    suspend fun backsounds(): Response<ApiWrapper<List<Backsound>>>

    @Multipart
    @POST("backsounds")
    suspend fun uploadBacksound(
        @Part("name") name: RequestBody,
        @Part file: MultipartBody.Part
    ): Response<ApiWrapper<Backsound>>
}
