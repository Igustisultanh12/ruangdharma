package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.TokenUsage
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

/** Padanan bagian "Penggunaan Token Muse" di views/Admin/MuseConfig.vue (khusus admin). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MuseScreen(usage: TokenUsage?, error: String?, onRefresh: () -> Unit) {
    val c = Rd.colors
    PullToRefreshBox(isRefreshing = false, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            item { PageHeader("Integrasi Muse", "Pantau pemakaian token Muse untuk naskah & caption. Terbarui otomatis.") }
            if (usage == null) {
                item {
                    if (error != null) RdCard(Modifier.fillMaxWidth()) { ErrorBox(error, onRefresh) }
                    else Column(verticalArrangement = Arrangement.spacedBy(12.dp)) { ShimmerCard(); ShimmerCard() }
                }
                return@LazyColumn
            }
            item {
                BrutalCard(Modifier.fillMaxWidth()) {
                    Eyebrow("Bulan ini", icon = Icons.Filled.DataUsage)
                    Row(Modifier.padding(top = 12.dp), verticalAlignment = Alignment.Bottom) {
                        Text(formatNumber(usage.monthTokens), fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 32.sp, color = c.ink)
                        Text(" / ${formatNumber(usage.monthLimit)} token", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(bottom = 6.dp))
                    }
                    QuotaBar(
                        used = (usage.percentUsed * 10).toInt().coerceAtLeast(0), limit = 1000,
                        label = "Terpakai ${usage.percentUsed}%", modifier = Modifier.padding(top = 10.dp),
                    )
                    Text("Sisa ${formatNumber(usage.remaining)} token", fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 8.dp))
                }
            }
            item {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard("Total token", formatNumber(usage.totalTokens), Icons.Filled.Analytics, "violet", "estimasi seluruh waktu", modifier = Modifier.weight(1f))
                        StatCard("Video", formatNumber(usage.totalGenerations.toLong()), Icons.Filled.Movie, "orange", "semua pengguna", modifier = Modifier.weight(1f))
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard("Job Muse", formatNumber(usage.totalJobs.toLong()), Icons.Filled.CloudSync, "sky", "dikirim ke Muse", modifier = Modifier.weight(1f))
                        StatCard("Sisa bulan ini", formatNumber(usage.remaining), Icons.Filled.AccountBalanceWallet, "emerald", "token", modifier = Modifier.weight(1f))
                    }
                }
            }
            item { SectionTitle("Riwayat per video", Modifier.padding(top = 6.dp)) }
            val history = usage.history.orEmpty()
            if (history.isEmpty()) item { Text("Belum ada riwayat.", fontSize = 13.sp, color = c.muted) }
            items(history, key = { it.id }) { h ->
                val sh = RoundedCornerShape(16.dp)
                Row(
                    Modifier.fillMaxWidth().clip(sh).border(1.dp, c.line, sh).padding(14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(h.keyword, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = c.ink)
                        Text("${modelInfo(h.model).label} • ${h.durasi} dtk${h.museJobId?.let { " • job #$it" } ?: ""} • ${relativeTime(h.createdAt)}", fontSize = 12.sp, color = c.muted)
                    }
                    RdBadge("${formatNumber(h.tokens.toLong())} token", "violet", small = true)
                }
            }
            item {
                Text("Estimasi: 1 token ≈ 4 karakter. Ini pemakaian untuk naskah/caption yang dihasilkan web, bukan billing resmi Muse.", fontSize = 11.sp, color = c.muted)
                Spacer(Modifier.height(8.dp))
            }
        }
    }
}
