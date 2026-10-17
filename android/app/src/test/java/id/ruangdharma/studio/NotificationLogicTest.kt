package id.ruangdharma.studio

import id.ruangdharma.studio.data.api.LenientGson
import id.ruangdharma.studio.data.model.MobileConfig
import id.ruangdharma.studio.data.model.NotificationsResponse
import id.ruangdharma.studio.notify.NotificationHelper
import org.junit.Assert.*
import org.junit.Test

class NotificationLogicTest {
    private fun item(id: Int, read: Boolean = false) = NotificationHelper.Item(id, "Judul $id", "Pesan", read)

    @Test fun newUnread_onlyNewerAndUnread_newestFirst() {
        val items = listOf(item(5), item(7, read = true), item(9), item(3))
        val fresh = NotificationHelper.newUnread(items, lastId = 4)
        assertEquals(listOf(9, 5), fresh.map { it.id })
    }

    @Test fun channelFor_mapsServerTitles() {
        assertEquals(NotificationHelper.CHANNEL_VIDEO, NotificationHelper.channelFor("Video via Muse selesai", "Video \"Karma\" sudah jadi"))
        assertEquals(NotificationHelper.CHANNEL_REVIEW, NotificationHelper.channelFor("Naskah menunggu review", "Budi mengirim Karma"))
        assertEquals(NotificationHelper.CHANNEL_REVIEW, NotificationHelper.channelFor("Komentar baru", "Ayu berkomentar"))
        assertEquals(NotificationHelper.CHANNEL_GENERAL, NotificationHelper.channelFor("Akun dibuat", "Selamat datang"))
    }

    @Test fun parsesNotificationsAndMobileConfig() {
        val n = LenientGson.gson.fromJson(
            """{"success":true,"unread_count":2,"data":{"current_page":1,"data":[{"id":11,"title":"Video via Muse selesai","message":"ok","is_read":0,"created_at":"2026-10-17T10:00:00.000000Z"}]}}""",
            NotificationsResponse::class.java,
        )
        assertEquals(2, n.unreadCount)
        assertEquals(11, n.data?.items?.first()?.id)
        assertFalse(n.data!!.items.first().isRead)

        val cfg = LenientGson.gson.fromJson("""{"success":true,"push":{"api_key":"A","app_id":"1:2:android:3","project_id":"rd","sender_id":"123"},"push_enabled":true}""", MobileConfig::class.java)
        assertTrue(cfg.push!!.isComplete)
        val empty = LenientGson.gson.fromJson("""{"success":true,"push":null,"push_enabled":false}""", MobileConfig::class.java)
        assertNull(empty.push)
    }
}
