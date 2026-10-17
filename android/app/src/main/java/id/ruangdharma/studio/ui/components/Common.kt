package id.ruangdharma.studio.ui.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.statusLabel

@Composable
fun CanangMark(modifier: Modifier = Modifier, size: Int = 48) {
    // Canang: 5 kelopak - Timur putih, Selatan merah, Barat kuning, Utara biru, Tengah rampai
    Box(modifier.size(size.dp), contentAlignment = Alignment.Center) {
        Text("🌸", fontSize = (size - 8).sp)
    }
}

@Composable
fun StatusBadge(status: String) {
    val (bg, fg) = when (status) {
        "waiting_muse" -> Color(0xFFE0F2FE) to Color(0xFF0369A1)
        "processing" -> Color(0xFFEDE9FE) to Color(0xFF6D28D9)
        "completed" -> Color(0xFFDCFCE7) to Color(0xFF166534)
        "failed" -> Color(0xFFFEE2E2) to Color(0xFF991B1B)
        else -> Color(0xFFF3F4F6) to Color(0xFF374151)
    }
    Surface(color = bg, shape = RoundedCornerShape(999.dp)) {
        Text(statusLabel(status), color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp))
    }
}

@Composable
fun ShimmerCard(modifier: Modifier = Modifier) {
    val infinite = rememberInfiniteTransition(label = "shimmer")
    val alpha by infinite.animateFloat(0.3f, 1f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "a")
    Card(modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp)) {
        Column(Modifier.padding(16.dp)) {
            Box(Modifier.fillMaxWidth(0.6f).height(16.dp).clip(RoundedCornerShape(4.dp)).background(Color.LightGray.copy(alpha = alpha)))
            Spacer(Modifier.height(8.dp))
            Box(Modifier.fillMaxWidth().height(120.dp).clip(RoundedCornerShape(12.dp)).background(Color.LightGray.copy(alpha = alpha)))
        }
    }
}

@Composable
fun ErrorBox(message: String, onRetry: () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text("⚠️", fontSize = 32.sp)
        Text(message, style = MaterialTheme.typography.bodyMedium)
        Spacer(Modifier.height(12.dp))
        Button(onClick = onRetry) { Text("Coba lagi") }
    }
}
