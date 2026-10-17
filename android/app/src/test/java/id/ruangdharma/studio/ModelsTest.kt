package id.ruangdharma.studio

import com.google.gson.Gson
import id.ruangdharma.studio.data.api.LenientGson
import id.ruangdharma.studio.data.model.*
import org.junit.Assert.*
import org.junit.Test

class ModelsTest {
    private val gson: Gson = LenientGson.gson

    @Test fun statusLabel_mapsWaitingMuse() {
        assertEquals("Menunggu Muse", statusLabel("waiting_muse"))
        assertEquals("Muse memproses", statusLabel("processing"))
        assertEquals("Selesai", statusLabel("completed"))
    }
    @Test fun generation_captionText_handlesString() {
        val g = Generation(caption = "Hello")
        assertEquals("Hello", g.captionText())
    }
    @Test fun generation_fullVideoUrl_prefixesRelative() {
        val g = Generation(videoUrl = "/storage/videos/x.mp4")
        assertEquals("https://ruangdharma.my.id/storage/videos/x.mp4", g.fullVideoUrl())
    }
    @Test fun generation_fullVideoUrl_keepsAbsolute() {
        val g = Generation(videoUrl = "https://ruangdharma.my.id/storage/videos/x.mp4")
        assertEquals("https://ruangdharma.my.id/storage/videos/x.mp4", g.fullVideoUrl())
    }

    /** Respons nyata POST /api/login: token & user di level atas + salinan di "data". */
    @Test fun loginResponse_readsTopLevelToken() {
        val json = """{"success":true,"message":"Login berhasil.","user":{"id":1,"name":"Admin Dharma","email":"admin@ruangdharma.id","role":"admin","is_admin":true,"quota":{"limit":20,"used_today":2,"remaining":18}},"token":"1|abc","data":{"user":{"id":1},"token":"1|abc"}}"""
        val r = gson.fromJson(json, LoginResponse::class.java)
        assertEquals("1|abc", r.resolvedToken())
        assertEquals("Admin", r.resolvedUser()?.firstName)
        assertTrue(r.resolvedUser()?.isAdmin == true)
        assertEquals(18, r.resolvedUser()?.quota?.remaining)
    }
    @Test fun loginResponse_fallsBackToDataToken() {
        val r = gson.fromJson("""{"success":true,"data":{"token":"2|xyz","user":{"id":2,"name":"Budi"}}}""", LoginResponse::class.java)
        assertEquals("2|xyz", r.resolvedToken())
        assertEquals("Budi", r.resolvedUser()?.name)
    }
    /** GET /api/me mengirim {success, user} — bukan dibungkus "data". */
    @Test fun meResponse_readsUser() {
        val r = gson.fromJson("""{"success":true,"user":{"id":3,"name":"Ni Luh Putu","role":"editor","is_reviewer":true}}""", MeResponse::class.java)
        assertEquals("Ni", r.resolvedUser()?.firstName)
        assertEquals("Editor", r.resolvedUser()?.roleLabel)
    }
    @Test fun generation_parsesWorkflowAndNaskah() {
        val json = """{"id":9,"keyword":"Karma","status":"completed","workflow_status":"revisi","is_favorite":true,"caption":{"text":"Halo"},"naskah":[{"bagian":1,"speaker":"Narator","teks":"Om Swastyastu","prompt_visual":"p"}]}"""
        val g = gson.fromJson(json, Generation::class.java)
        assertEquals("revisi", g.workflowStatus)
        assertTrue(g.isFavorite)
        assertEquals("Halo", g.captionText())
        assertEquals("Om Swastyastu", g.naskah?.first()?.text())
        assertFalse(g.isPendingMuse)
    }
    @Test fun socialStats_readsPostsAndComments() {
        val json = """{"success":true,"platform":"instagram","connected":true,"data":{"platform":"instagram","followers":120,"posts":[{"caption":"A","likes":5}],"recent_comments":[{"author":"x","text":"mantap"}]}}"""
        val r = gson.fromJson(json, StatsResponse::class.java)
        assertEquals(120, r.data?.followers)
        assertEquals(5L, r.data?.postList()?.first()?.num("likes"))
        assertEquals("mantap", r.data?.commentList()?.first()?.str("text"))
    }

    /** Bug v1.1.0: Muse mengirim estimasi_detik = 3.1 → seluruh Riwayat gagal dimuat. */
    @Test fun generationList_toleratesFloatsStringsAndObjects() {
        val json = """{"success":true,"data":{"current_page":1,"total":1,"data":[{"id":"7","keyword":"Karma","durasi":60.0,"backsound_volume":"25","is_favorite":1,"review_note":{"text":"cek lagi"},"naskah":[{"bagian":1,"estimasi_detik":3.1,"teks":"Halo"}]}]}}"""
        val type = object : com.google.gson.reflect.TypeToken<ApiWrapper<Paginator<Generation>>>() {}.type
        val r: ApiWrapper<Paginator<Generation>> = gson.fromJson(json, type)
        val g = r.data!!.items.first()
        assertEquals(7, g.id)
        assertEquals(60, g.durasi)
        assertEquals(25, g.backsoundVolume)
        assertTrue(g.isFavorite)
        assertEquals("cek lagi", g.reviewNote)
        assertEquals(3, g.naskah?.first()?.estimasiDetik)
    }

    /** Kasus Riwayat gagal dimuat: naskah dikirim sebagai objek asosiatif PHP {"0": ...}, string kosong "", array string, atau teks utuh. */
    @Test fun generationList_toleratesNaskahAssociativeObject() {
        val json = """{"success":true,"data":{"current_page":1,"total":1,"data":[{"id":1,"keyword":"Tri Sandhya","durasi":60,"naskah":{"0":{"bagian":1,"teks":"Om Swastyastu"},"1":{"bagian":2,"teks":"Pagi hari"}}}]}}"""
        val type = object : com.google.gson.reflect.TypeToken<ApiWrapper<Paginator<Generation>>>() {}.type
        val r: ApiWrapper<Paginator<Generation>> = gson.fromJson(json, type)
        val g = r.data!!.items.first()
        assertEquals(2, g.naskah?.size)
        assertEquals("Om Swastyastu", g.naskah?.first()?.text())
    }

    @Test fun generationList_toleratesNaskahEmptyStringAndNull() {
        val json = """{"success":true,"data":{"current_page":1,"total":2,"data":[{"id":1,"keyword":"A","durasi":60,"naskah":""},{"id":2,"keyword":"B","durasi":60,"naskah":null}]}}"""
        val type = object : com.google.gson.reflect.TypeToken<ApiWrapper<Paginator<Generation>>>() {}.type
        val r: ApiWrapper<Paginator<Generation>> = gson.fromJson(json, type)
        assertEquals(2, r.data!!.items.size)
        assertEquals(0, r.data!!.items[0].naskah?.size)
        assertNull(r.data!!.items[1].naskah)
    }

    @Test fun generationList_toleratesNaskahArrayOfStrings() {
        val json = """{"success":true,"data":{"current_page":1,"total":1,"data":[{"id":1,"keyword":"Karma","durasi":60,"naskah":["Bagian 1 cerita...","Bagian 2 cerita..."]}]}}"""
        val type = object : com.google.gson.reflect.TypeToken<ApiWrapper<Paginator<Generation>>>() {}.type
        val r: ApiWrapper<Paginator<Generation>> = gson.fromJson(json, type)
        val g = r.data!!.items.first()
        assertEquals(2, g.naskah?.size)
        assertEquals("Bagian 1 cerita...", g.naskah?.first()?.text())
        assertEquals(1, g.naskah?.first()?.bagian)
        assertEquals(2, g.naskah?.get(1)?.bagian)
    }

    @Test fun generationList_toleratesNaskahPlainText() {
        val json = """{"success":true,"data":{"current_page":1,"total":1,"data":[{"id":1,"keyword":"Karma","durasi":60,"naskah":"Naskah utuh dari generator"}]}}"""
        val type = object : com.google.gson.reflect.TypeToken<ApiWrapper<Paginator<Generation>>>() {}.type
        val r: ApiWrapper<Paginator<Generation>> = gson.fromJson(json, type)
        val g = r.data!!.items.first()
        assertEquals(1, g.naskah?.size)
        assertEquals("Naskah utuh dari generator", g.naskah?.first()?.text())
    }
}
