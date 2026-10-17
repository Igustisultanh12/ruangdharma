package id.ruangdharma.studio.data.model

import com.google.gson.annotations.SerializedName

data class ApiWrapper<T>(
    @SerializedName("success") val success: Boolean = true,
    @SerializedName("data") val data: T? = null,
    @SerializedName("message") val message: String? = null
)

data class LoginResponse(
    @SerializedName("success") val success: Boolean = true,
    @SerializedName("message") val message: String? = null,
    // Server mengirim token & user di level ATAS (bukan di dalam "data").
    // Field "data" dipertahankan sebagai fallback bila server mengirim wrapper.
    @SerializedName("token") val token: String? = null,
    @SerializedName("user") val user: User? = null,
    @SerializedName("data") val data: LoginData? = null
) {
    fun resolvedToken(): String? = token ?: data?.token
    fun resolvedUser(): User? = user ?: data?.user
}

data class LoginData(
    @SerializedName("token") val token: String? = null,
    @SerializedName("user") val user: User? = null
)

data class User(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String = "",
    @SerializedName("email") val email: String = "",
    @SerializedName("role") val role: String = "pengguna",
    @SerializedName("avatar_url") val avatarUrl: String? = null,
    @SerializedName("quota") val quota: Quota? = null
)

data class Quota(
    @SerializedName("limit") val limit: Int = 0,
    @SerializedName("used_today") val usedToday: Int = 0,
    @SerializedName("remaining") val remaining: Int = 0
)

data class Paginator<T>(
    @SerializedName("data") val items: List<T> = emptyList(),
    @SerializedName("current_page") val currentPage: Int = 1,
    @SerializedName("last_page") val lastPage: Int = 1,
    @SerializedName("total") val total: Int = 0
)

data class Generation(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("keyword") val keyword: String = "",
    @SerializedName("durasi") val durasi: Int = 60,
    @SerializedName("model") val model: String = "narasi",
    @SerializedName("status") val status: String = "",
    @SerializedName("caption") val caption: Any? = null,
    @SerializedName("naskah") val naskah: List<NaskahPart>? = null,
    @SerializedName("video_url") val videoUrl: String? = null,
    @SerializedName("video_files") val videoFiles: Any? = null,
    @SerializedName("backsound_url") val backsoundUrl: String? = null,
    @SerializedName("backsound_volume") val backsoundVolume: Int? = 20,
    @SerializedName("backsound_trim_start") val backsoundTrimStart: Int? = 0,
    @SerializedName("backsound_trim_end") val backsoundTrimEnd: Int? = null,
    @SerializedName("muse_job_id") val museJobId: Int? = null,
    @SerializedName("created_at") val createdAt: String? = null
) {
    fun captionText(): String = when (caption) {
        is String -> caption as String
        is Map<*, *> -> (caption as Map<*, *>)[ "text" ]?.toString() ?: ""
        else -> ""
    }
    fun fullVideoUrl(): String? = videoUrl?.let {
        if (it.startsWith("http")) it else "https://ruangdharma.my.id" + it
    }
}

data class NaskahPart(
    @SerializedName("bagian") val bagian: Int = 0,
    @SerializedName("judul") val judul: String? = null,
    @SerializedName("teks") val teks: String? = null,
    @SerializedName("dialog") val dialog: String? = null,
    @SerializedName("narasi") val narasi: String? = null,
    @SerializedName("speaker") val speaker: String? = null
) {
    fun text(): String = teks ?: dialog ?: narasi ?: ""
}

data class SocialStats(
    @SerializedName("platform") val platform: String = "",
    @SerializedName("account_username") val username: String? = null,
    @SerializedName("followers") val followers: Int = 0,
    @SerializedName("total_likes") val totalLikes: Int = 0,
    @SerializedName("total_comments") val totalComments: Int = 0,
    @SerializedName("reach") val reach: Int = 0,
    @SerializedName("views") val views: Int = 0
)

data class SocialPublish(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("platform") val platform: String = "",
    @SerializedName("status") val status: String = "",
    @SerializedName("caption") val caption: String? = null,
    @SerializedName("post_url") val postUrl: String? = null,
    @SerializedName("generation_id") val generationId: Int? = null
)

data class TokenUsage(
    @SerializedName("total_tokens") val totalTokens: Long = 0,
    @SerializedName("total_generations") val totalGenerations: Int = 0,
    @SerializedName("total_jobs") val totalJobs: Int = 0,
    @SerializedName("month_tokens") val monthTokens: Long = 0,
    @SerializedName("month_limit") val monthLimit: Long = 22000000,
    @SerializedName("remaining") val remaining: Long = 0,
    @SerializedName("percent_used") val percentUsed: Double = 0.0,
    @SerializedName("history") val history: List<TokenHistoryItem>? = null
)

data class TokenHistoryItem(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("keyword") val keyword: String = "",
    @SerializedName("model") val model: String = "",
    @SerializedName("durasi") val durasi: Int = 60,
    @SerializedName("muse_job_id") val museJobId: Int? = null,
    @SerializedName("chars") val chars: Int = 0,
    @SerializedName("tokens") val tokens: Int = 0,
    @SerializedName("created_at") val createdAt: String? = null
)

data class Backsound(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String = "",
    @SerializedName("file_url") val fileUrl: String? = null,
    @SerializedName("duration") val duration: Int? = null
)

fun statusLabel(status: String): String = when (status) {
    "waiting_muse" -> "Menunggu Muse"
    "processing" -> "Muse memproses"
    "completed" -> "Selesai"
    "failed" -> "Gagal"
    "draft" -> "Draf"
    else -> status
}
