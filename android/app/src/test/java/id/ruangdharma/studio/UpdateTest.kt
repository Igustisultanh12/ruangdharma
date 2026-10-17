package id.ruangdharma.studio

import id.ruangdharma.studio.data.update.AppUpdater
import org.junit.Assert.*
import org.junit.Test

class UpdateTest {

    @Test
    fun compareVersions_handlesNumericHierarchy() {
        assertTrue(AppUpdater.compareVersions("1.3.0", "1.2.1") > 0)
        assertTrue(AppUpdater.compareVersions("1.10.0", "1.9.2") > 0)
        assertTrue(AppUpdater.compareVersions("v1.3.0", "1.2.1") > 0)
        assertEquals(0, AppUpdater.compareVersions("1.3.0", "1.3.0"))
        assertEquals(0, AppUpdater.compareVersions("v1.3.0", "1.3.0"))
        assertEquals(0, AppUpdater.compareVersions("1.3.0-debug", "1.3.0"))
        assertTrue(AppUpdater.compareVersions("1.2.1", "1.3.0") < 0)
        assertTrue(AppUpdater.compareVersions("1.2.1", "1.2.2") < 0)
    }

    @Test
    fun parseServer_parsesValidPayload() {
        val sha = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        val json = """
            {
                "status": "success",
                "available": true,
                "version": "1.3.0",
                "version_code": 6,
                "notes": "Pembaruan otomatis",
                "size": 18500000,
                "sha256": "$sha",
                "file_name": "RUANGDHARMA-1.3.0.apk",
                "download_url": "/api/mobile/app-update/download?v=1.3.0"
            }
        """.trimIndent()

        val info = AppUpdater.parseServer(json, "https://ruangdharma.my.id")
        assertNotNull(info)
        assertEquals("1.3.0", info?.version)
        assertEquals(6, info?.versionCode)
        assertEquals(18500000L, info?.apkSize)
        assertEquals(sha, info?.sha256)
        assertEquals("https://ruangdharma.my.id/api/mobile/app-update/download?v=1.3.0", info?.apkUrl)
        assertEquals("RUANGDHARMA-1.3.0.apk", info?.apkName)
    }

    @Test
    fun parseServer_handlesAbsoluteGitHubUrl() {
        val sha = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        val json = """
            {
                "status": "success",
                "available": true,
                "version": "1.3.0",
                "version_code": 6,
                "size": 19000000,
                "sha256": "$sha",
                "download_url": "https://github.com/Igustisultanh12/ruangdharma/releases/download/v1.3.0/RUANGDHARMA-1.3.0.apk"
            }
        """.trimIndent()

        val info = AppUpdater.parseServer(json, "https://ruangdharma.my.id")
        assertNotNull(info)
        assertEquals("https://github.com/Igustisultanh12/ruangdharma/releases/download/v1.3.0/RUANGDHARMA-1.3.0.apk", info?.apkUrl)
    }

    @Test
    fun parseServer_rejectsInvalidOrUnavailable() {
        assertNull(AppUpdater.parseServer("""{"status":"success","available":false}"""))
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"invalid","sha256":"e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"}"""))
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"1.3.0","sha256":"not-a-valid-sha256"}"""))
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"1.3.0","sha256":""}"""))
    }

    @Test
    fun reason_formatsUserFriendlyMessages() {
        assertTrue(AppUpdater.reason(java.net.UnknownHostException("ruangdharma.my.id")).contains("koneksi internet"))
        assertTrue(AppUpdater.reason(java.net.SocketTimeoutException()).contains("terlalu lama"))
        assertTrue(AppUpdater.reason(SecurityException("Permission denied")).contains("Android"))
    }
}
