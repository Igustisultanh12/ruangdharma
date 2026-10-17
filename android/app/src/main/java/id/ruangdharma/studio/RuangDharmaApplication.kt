package id.ruangdharma.studio

import android.app.Activity
import android.app.Application
import android.os.Bundle
import id.ruangdharma.studio.notify.NotificationHelper
import id.ruangdharma.studio.notify.PushManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob

class RuangDharmaApplication : Application(), Application.ActivityLifecycleCallbacks {

    val appScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)

    companion object {
        var isAppForeground: Boolean = false
            private set
    }

    private var resumedActivities = 0

    override fun onCreate() {
        super.onCreate()
        registerActivityLifecycleCallbacks(this)
        NotificationHelper.createChannels(this)
        // Firebase diinisialisasi dari konfigurasi yang dikirim server (disimpan saat login).
        PushManager.initFromCache(this)
    }

    override fun onActivityResumed(activity: Activity) {
        resumedActivities++
        isAppForeground = resumedActivities > 0
    }

    override fun onActivityPaused(activity: Activity) {
        resumedActivities = (resumedActivities - 1).coerceAtLeast(0)
        isAppForeground = resumedActivities > 0
    }

    override fun onActivityCreated(activity: Activity, savedInstanceState: Bundle?) {}
    override fun onActivityStarted(activity: Activity) {}
    override fun onActivityStopped(activity: Activity) {}
    override fun onActivitySaveInstanceState(activity: Activity, outState: Bundle) {}
    override fun onActivityDestroyed(activity: Activity) {}
}
