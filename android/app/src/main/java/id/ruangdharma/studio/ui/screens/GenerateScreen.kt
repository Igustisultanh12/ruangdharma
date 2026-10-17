package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

@Composable
fun GenerateScreen(onSubmit: (String, Int, String, Boolean, Int, Int, Int?) -> Unit, submitting: Boolean, result: String?) {
    var keyword by remember { mutableStateOf("") }
    var durasi by remember { mutableStateOf(60) }
    var model by remember { mutableStateOf("narasi") }
    var viaMuse by remember { mutableStateOf(true) }
    var volume by remember { mutableStateOf(20f) }
    var trimStart by remember { mutableStateOf("0") }
    var trimEnd by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp)) {
        Text("Buat Video Baru", style = MaterialTheme.typography.headlineSmall)
        Spacer(Modifier.height(12.dp))
        OutlinedTextField(keyword, { keyword = it }, label = { Text("Tema / kata kunci (mis. Rwa Bhineda)") }, modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(12.dp))
        Text("Durasi", style = MaterialTheme.typography.labelLarge)
        Row { listOf(30, 60, 90).forEach { d -> FilterChip(durasi == d, { durasi = d }, { Text("$d dtk") }, modifier = Modifier.padding(end = 8.dp)) } }
        Spacer(Modifier.height(8.dp))
        Text("Gaya penyampaian", style = MaterialTheme.typography.labelLarge)
        Row { listOf("narasi" to "Narasi", "ngobrol" to "Ngobrol", "gabungan" to "Gabungan").forEach { (v, l) -> FilterChip(model == v, { model = v }, { Text(l) }, modifier = Modifier.padding(end = 8.dp)) } }
        Spacer(Modifier.height(12.dp))
        Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
            Checkbox(viaMuse, { viaMuse = it })
            Text("Buat video beneran via Muse")
        }
        Spacer(Modifier.height(12.dp))
        Text("🎵 Backsound", style = MaterialTheme.typography.titleMedium)
        Text("Volume backsound: " + volume.toInt() + "%", style = MaterialTheme.typography.bodySmall)
        Slider(volume, { volume = it }, valueRange = 0f..100f)
        Row { 
            OutlinedTextField(trimStart, { trimStart = it }, label = { Text("Mulai (detik)") }, modifier = Modifier.weight(1f).padding(end = 8.dp))
            OutlinedTextField(trimEnd, { trimEnd = it }, label = { Text("Sampai (detik, kosong = habis)") }, modifier = Modifier.weight(1f))
        }
        Text("Backsound dipotong / di-loop otomatis agar pas durasi video, dicampur di bawah narasi.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(16.dp))
        Button(onClick = { onSubmit(keyword.trim(), durasi, model, viaMuse, volume.toInt(), trimStart.toIntOrNull() ?: 0, trimEnd.toIntOrNull()) }, enabled = !submitting && keyword.trim().length > 1, modifier = Modifier.fillMaxWidth()) {
            if (submitting) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp) else Text(if (viaMuse) "Kirim ke Muse" else "Buat Video")
        }
        if (result != null) { Spacer(Modifier.height(12.dp)); Text(result, style = MaterialTheme.typography.bodyMedium) }
    }
}
