package id.ruangdharma.studio

import android.app.Application
import id.ruangdharma.studio.notify.NotificationHelper
import id.ruangdharma.studio.notify.PushManager

class RuangDharmaApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        NotificationHelper.createChannels(this)
        // Firebase diinisialisasi dari konfigurasi yang dikirim server (disimpan saat login).
        PushManager.initFromCache(this)
    }
}
