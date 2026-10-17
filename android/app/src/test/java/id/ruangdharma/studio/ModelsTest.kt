package id.ruangdharma.studio

import id.ruangdharma.studio.data.model.*
import org.junit.Assert.*
import org.junit.Test

class ModelsTest {
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
}
