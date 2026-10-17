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
    @SerializedName("quota") val quota: Quota? = null,
    @SerializedName("is_admin") val isAdmin: Boolean = false,
    @SerializedName("is_reviewer") val isReviewer: Boolean = false,
    @SerializedName("default_durasi") val defaultDurasi: Int? = null,
    @SerializedName("default_model") val defaultModel: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
) {
    val firstName: String get() = name.trim().split(" ").firstOrNull().orEmpty().ifBlank { "Sahabat" }
    val roleLabel: String get() = when (role) {
        "super_admin" -> "Super Admin"; "admin" -> "Admin"; "editor" -> "Editor"; else -> "Pengguna"
    }
}

/** GET /me → {success, user} (tidak dibungkus "data"). */
data class MeResponse(
    @SerializedName("success") val success: Boolean = true,
    @SerializedName("user") val user: User? = null,
    @SerializedName("data") val data: User? = null
) { fun resolvedUser(): User? = user ?: data }

/** GET /realtime/pulse — stempel per sumber data; berubah = muat ulang tanpa refresh. */
data class Pulse(
    @SerializedName("active") val active: Boolean = true,
    @SerializedName("stamps") val stamps: Map<String, String>? = null,
    @SerializedName("unread_notifications") val unread: Int = 0,
    @SerializedName("quota") val quota: PulseQuota? = null
)

data class PulseQuota(
    @SerializedName("limit") val limit: Int = 0,
    @SerializedName("used") val used: Int = 0,
    @SerializedName("remaining") val remaining: Int = 0
)

data class Counts(
    @SerializedName("all") val all: Int = 0,
    @SerializedName("favorites") val favorites: Int = 0,
    @SerializedName("archived") val archived: Int = 0,
    @SerializedName("review") val review: Int = 0,
    @SerializedName("revisi") val revisi: Int = 0,
    @SerializedName("published") val published: Int = 0
)

/** GET /social/stats?platform=… → {success, platform, data: SocialStats|null, connected, hint} */
data class StatsResponse(
    @SerializedName("success") val success: Boolean = true,
    @SerializedName("platform") val platform: String? = null,
    @SerializedName("data") val data: SocialStats? = null,
    @SerializedName("connected") val connected: Boolean = false,
    @SerializedName("hint") val hint: String? = null
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
    @SerializedName("workflow_status") val workflowStatus: String? = null,
    @SerializedName("is_favorite") val isFavorite: Boolean = false,
    @SerializedName("is_archived") val isArchived: Boolean = false,
    @SerializedName("review_note") val reviewNote: String? = null,
    @SerializedName("source") val source: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
) {
    val isPendingMuse: Boolean get() = status == "waiting_muse" || status == "processing"
    fun captionText(): String = when (caption) {
        is String -> caption as String
        is Map<*, *> -> ((caption as Map<*, *>)["text"] ?: (caption as Map<*, *>)["caption"])?.toString() ?: ""
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
    @SerializedName("speaker") val speaker: String? = null,
    @SerializedName("suara") val suara: String? = null,
    @SerializedName("prompt_visual") val promptVisual: String? = null,
    @SerializedName("estimasi_detik") val estimasiDetik: Int? = null
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
    @SerializedName("views") val views: Int = 0,
    @SerializedName("fetched_at") val fetchedAt: String? = null,
    @SerializedName("posts") val posts: Any? = null,
    @SerializedName("recent_comments") val recentComments: Any? = null
) {
    fun postList(): List<Map<*, *>> = (posts as? List<*>)?.filterIsInstance<Map<*, *>>() ?: emptyList()
    fun commentList(): List<Map<*, *>> = (recentComments as? List<*>)?.filterIsInstance<Map<*, *>>() ?: emptyList()
}

/** Ambil string pertama yang tidak kosong dari beberapa kunci JSON. */
fun Map<*, *>.str(vararg keys: String): String? = keys.firstNotNullOfOrNull { k -> this[k]?.toString()?.takeIf { it.isNotBlank() && it != "null" } }
fun Map<*, *>.num(vararg keys: String): Long = keys.firstNotNullOfOrNull { k -> (this[k] as? Number)?.toLong() ?: this[k]?.toString()?.toDoubleOrNull()?.toLong() } ?: 0L

data class SocialPublish(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("platform") val platform: String = "",
    @SerializedName("status") val status: String = "",
    @SerializedName("caption") val caption: String? = null,
    @SerializedName("post_url") val postUrl: String? = null,
    @SerializedName("generation_id") val generationId: Int? = null,
    @SerializedName("error") val error: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("generation") val generation: PublishGeneration? = null
)

data class PublishGeneration(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("keyword") val keyword: String? = null
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
    @SerializedName("duration_sec") val duration: Int? = null
)

fun statusLabel(status: String): String = when (status) {
    "waiting_muse" -> "Menunggu Muse"
    "processing" -> "Muse memproses"
    "completed" -> "Selesai"
    "failed" -> "Muse gagal"
    "draft" -> "Draf"
    else -> status
}

/* ---------------- Notifikasi ---------------- */

data class AppNotification(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("title") val title: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("created_at") val createdAt: String? = null
)

/** GET /notifications → {success, unread_count, data: Paginator<AppNotification>} */
data class NotificationsResponse(
    @SerializedName("success") val success: Boolean = true,
    @SerializedName("unread_count") val unreadCount: Int = 0,
    @SerializedName("data") val data: Paginator<AppNotification>? = null
)

/** GET /mobile/config → konfigurasi klien Firebase dari server (diunggah admin). */
data class MobileConfig(
    @SerializedName("push") val push: PushConfig? = null,
    @SerializedName("push_enabled") val pushEnabled: Boolean = false
)

data class PushConfig(
    @SerializedName("api_key") val apiKey: String? = null,
    @SerializedName("app_id") val appId: String? = null,
    @SerializedName("project_id") val projectId: String? = null,
    @SerializedName("sender_id") val senderId: String? = null
) {
    val isComplete: Boolean get() = !apiKey.isNullOrBlank() && !appId.isNullOrBlank() && !projectId.isNullOrBlank() && !senderId.isNullOrBlank()
}
