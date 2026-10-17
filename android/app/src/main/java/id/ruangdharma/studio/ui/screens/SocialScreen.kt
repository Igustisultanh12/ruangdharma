package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import id.ruangdharma.studio.data.model.*

@Composable
fun SocialScreen(stats: List<SocialStats>, publishes: List<SocialPublish>, onPublish: (Int, String) -> Unit, onRefresh: () -> Unit, generations: List<Generation>) {
    var tab by remember { mutableStateOf(0) }
    val tabs = listOf("Instagram", "Facebook", "TikTok (Manual)")
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Medsos", style = MaterialTheme.typography.headlineSmall)
        TabRow(tab) { tabs.forEachIndexed { i, t -> Tab(tab == i, { tab = i }, text = { Text(t) }) } }
        Spacer(Modifier.height(12.dp))
        if (tab < 2) {
            val s = stats.find { it.platform == if (tab == 0) "instagram" else "facebook" }
            if (s != null) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    StatBox("Followers", s.followers.toString()); StatBox("Like", s.totalLikes.toString()); StatBox("Komen", s.totalComments.toString()); StatBox("Reach", s.reach.toString())
                }
            } else Text("Statistik belum tersedia — tekan Refresh.")
            Spacer(Modifier.height(8.dp))
            Button(onClick = onRefresh) { Text("Refresh Statistik") }
            Spacer(Modifier.height(12.dp))
            Text("Publish video ke " + tabs[tab], style = MaterialTheme.typography.titleMedium)
            LazyColumn { items(generations.filter { it.videoUrl != null }) { g ->
                Card(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                    Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(g.keyword); Button(onClick = { onPublish(g.id, if (tab == 0) "instagram" else "facebook") }) { Text("Publish") }
                    }
                }
            } }
        } else {
            Text("TikTok manual: unduh video dari Riwayat, lalu upload lewat aplikasi TikTok.", style = MaterialTheme.typography.bodyMedium)
        }
        Spacer(Modifier.height(12.dp))
        Text("Riwayat Publish", style = MaterialTheme.typography.titleMedium)
        LazyColumn { items(publishes) { p -> Text(p.platform + " • " + p.status + " • " + (p.postUrl ?: "-"), style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(vertical = 2.dp)) } }
    }
}

@Composable
fun StatBox(label: String, value: String) {
    Card { Column(Modifier.padding(12.dp)) { Text(value, style = MaterialTheme.typography.titleMedium); Text(label, style = MaterialTheme.typography.bodySmall) } }
}
