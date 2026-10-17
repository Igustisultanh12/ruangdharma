package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.ui.components.*

@Composable
fun HistoryScreen(generations: List<Generation>, onOpen: (Generation) -> Unit) {
    LazyColumn(contentPadding = PaddingValues(vertical = 8.dp)) {
        items(generations) { g -> FeedCard(g, onOpen) }
    }
}

@Composable
fun DetailScreen(g: Generation, onBack: () -> Unit, onSaveAudio: (Int, Int, Int?) -> Unit, onPublish: (String) -> Unit) {
    var volume by remember { mutableStateOf((g.backsoundVolume ?: 20).toFloat()) }
    var trimStart by remember { mutableStateOf((g.backsoundTrimStart ?: 0).toString()) }
    var trimEnd by remember { mutableStateOf(g.backsoundTrimEnd?.toString() ?: "") }
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Row { TextButton(onClick = onBack) { Text("← Kembali") } }
        Text(g.keyword, style = MaterialTheme.typography.headlineSmall)
        StatusBadge(g.status)
        Spacer(Modifier.height(8.dp))
        if (g.fullVideoUrl() != null) {
            VideoPlayer(url = g.fullVideoUrl()!!)
        } else {
            Text("Video belum tersedia — job masih diproses Muse.", style = MaterialTheme.typography.bodyMedium)
        }
        Spacer(Modifier.height(12.dp))
        Text("Naskah", style = MaterialTheme.typography.titleMedium)
        (g.naskah ?: emptyList()).forEach { p -> Text("Bagian " + p.bagian + ": " + p.text(), style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(vertical = 2.dp)) }
        Spacer(Modifier.height(12.dp))
        Text("Caption", style = MaterialTheme.typography.titleMedium)
        Text(g.captionText(), style = MaterialTheme.typography.bodySmall)
        Spacer(Modifier.height(16.dp))
        Text("🎵 Editor Audio", style = MaterialTheme.typography.titleMedium)
        Text("Volume: " + volume.toInt() + "%", style = MaterialTheme.typography.bodySmall)
        Slider(volume, { volume = it }, valueRange = 0f..100f)
        Row {
            OutlinedTextField(trimStart, { trimStart = it }, label = { Text("Mulai (detik)") }, modifier = Modifier.weight(1f).padding(end = 8.dp))
            OutlinedTextField(trimEnd, { trimEnd = it }, label = { Text("Sampai") }, modifier = Modifier.weight(1f))
        }
        Spacer(Modifier.height(8.dp))
        Button(onClick = { onSaveAudio(volume.toInt(), trimStart.toIntOrNull() ?: 0, trimEnd.toIntOrNull()) }, modifier = Modifier.fillMaxWidth()) { Text("Simpan & Mix Ulang") }
        Spacer(Modifier.height(16.dp))
        Text("Publish", style = MaterialTheme.typography.titleMedium)
        Row { Button(onClick = { onPublish("instagram") }, modifier = Modifier.padding(end = 8.dp)) { Text("Instagram") }; Button(onClick = { onPublish("facebook") }, modifier = Modifier.padding(end = 8.dp)) { Text("Facebook") }; OutlinedButton(onClick = { onPublish("tiktok") }) { Text("TikTok (Manual)") } }
    }
}

@Composable
fun VideoPlayer(url: String) {
    // ExoPlayer via Media3 — placeholder player card (full player wired in release builds)
    Card(Modifier.fillMaxWidth().height(220.dp)) {
        Box(Modifier.fillMaxSize(), contentAlignment = androidx.compose.ui.Alignment.Center) {
            Column(horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally) {
                Text("▶️", style = MaterialTheme.typography.headlineLarge)
                Text("Video: " + url.take(60) + "…", style = MaterialTheme.typography.bodySmall)
                Text("Buka di browser / pemutar untuk menonton.", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
