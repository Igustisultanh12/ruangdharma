package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import id.ruangdharma.studio.data.model.TokenUsage

@Composable
fun MuseScreen(usage: TokenUsage?, onRefresh: () -> Unit) {
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Integrasi Muse", style = MaterialTheme.typography.headlineSmall)
        Text("Penggunaan Token", style = MaterialTheme.typography.titleMedium)
        if (usage == null) { Text("Memuat…"); Button(onClick = onRefresh) { Text("Refresh") }; return }
        Text("Bulan ini: " + usage.monthTokens + " / " + usage.monthLimit)
        LinearProgressIndicator(progress = { (usage.percentUsed / 100).toFloat().coerceIn(0f, 1f) }, modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp))
        Text("Sisa: " + usage.remaining + " token • Total: " + usage.totalTokens + " • Generasi: " + usage.totalGenerations + " • Job: " + usage.totalJobs)
        Spacer(Modifier.height(12.dp))
        Text("Riwayat per video (20 terbaru)", style = MaterialTheme.typography.titleMedium)
        LazyColumn { items(usage.history ?: emptyList()) { h ->
            Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(h.keyword + " #" + (h.museJobId ?: h.id))
                Text(h.tokens.toString() + " token")
            }
        } }
        Spacer(Modifier.height(8.dp))
        Text("Estimasi: 1 token ~ 4 karakter. Bukan billing resmi Muse.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}
