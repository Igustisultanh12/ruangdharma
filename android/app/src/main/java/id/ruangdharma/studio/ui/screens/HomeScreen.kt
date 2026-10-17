package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.Counts
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.data.model.PulseQuota
import id.ruangdharma.studio.data.model.User
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*
import java.util.Calendar

fun greeting(): String {
    val h = Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
    return when { h < 11 -> "Selamat pagi"; h < 15 -> "Selamat siang"; h < 19 -> "Selamat sore"; else -> "Selamat malam" }
}

val IDEAS = listOf(
    Triple("Rwa Bhineda", "Dua hal berbeda yang saling melengkapi", "narasi"),
    Triple("Canang Sari", "Arti warna bunga di tiap arah mata angin", "ngobrol"),
    Triple("Tri Sandhya", "Sembahyang tiga kali sehari", "gabungan"),
    Triple("Tri Hita Karana", "Tiga penyebab kebahagiaan", "narasi"),
)

/** Padanan views/Dashboard.vue. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    user: User?,
    quota: PulseQuota?,
    counts: Counts?,
    generations: List<Generation>,
    loading: Boolean,
    refreshing: Boolean,
    error: String?,
    onRefresh: () -> Unit,
    onOpen: (Generation) -> Unit,
    onNavigate: (String) -> Unit,
    onIdea: (String, String) -> Unit,
) {
    val c = Rd.colors
    PullToRefreshBox(isRefreshing = refreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            item {
                BrutalBox(Modifier.fillMaxWidth().padding(end = 5.dp, bottom = 5.dp), shape = RoundedCornerShape(32.dp), brush = BrandGradient) {
                    Box(Modifier.matchParentSize().dotBackground())
                    Box(Modifier.align(Alignment.BottomEnd).offset(x = 18.dp, y = 22.dp).rotate(12f)) { CanangMark(size = 120, frame = Color.White) }
                    Column(Modifier.padding(22.dp)) {
                        Text("${greeting()}, ${user?.firstName ?: "Sahabat"} 👋", color = Color.White.copy(alpha = 0.8f), fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        Text(
                            "Cerita dharma apa yang kita buat hari ini?",
                            fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 27.sp, lineHeight = 31.sp,
                            color = Color.White, modifier = Modifier.padding(top = 4.dp, end = 40.dp),
                        )
                        val text = buildAnnotatedString {
                            if (quota != null) {
                                append("Kamu masih bisa membuat ")
                                withStyle(SpanStyle(fontWeight = FontWeight.ExtraBold)) { append(quota.remaining.toString()) }
                                append(" video hari ini.")
                            }
                            if ((counts?.revisi ?: 0) > 0) {
                                append(" Ada ")
                                withStyle(SpanStyle(fontWeight = FontWeight.ExtraBold)) { append(counts!!.revisi.toString()) }
                                append(" naskah yang perlu direvisi.")
                            }
                        }
                        if (text.isNotEmpty()) Text(text, color = Color.White.copy(alpha = 0.88f), fontSize = 14.sp, modifier = Modifier.padding(top = 8.dp, end = 60.dp))
                        Spacer(Modifier.height(16.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                            BrutalButton("Buat video", { onNavigate("generate") }, icon = Icons.Filled.AutoFixHigh, small = true)
                            GlassButton("Riwayat", { onNavigate("history") }, icon = Icons.Filled.History)
                            GlassButton("Medsos", { onNavigate("social") }, icon = Icons.Filled.Share)
                        }
                    }
                }
            }

            item {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard("Dibuat hari ini", (quota?.used ?: 0).toString(), Icons.Filled.Bolt, "orange", if (quota != null) "dari kuota ${quota.limit} video" else "", loading = quota == null, modifier = Modifier.weight(1f), onClick = { onNavigate("generate") })
                        StatCard("Video aktif", (counts?.all ?: 0).toString(), Icons.Filled.Movie, "violet", "${counts?.archived ?: 0} di arsip", loading = counts == null, modifier = Modifier.weight(1f), onClick = { onNavigate("history") })
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard("Favorit", (counts?.favorites ?: 0).toString(), Icons.Filled.Star, "amber", "Siap dipakai lagi", loading = counts == null, modifier = Modifier.weight(1f), onClick = { onNavigate("history") })
                        StatCard("Terbit", (counts?.published ?: 0).toString(), Icons.Filled.Send, "fuchsia", "${counts?.review ?: 0} menunggu review", loading = counts == null, modifier = Modifier.weight(1f))
                    }
                }
            }

            item {
                SectionTitle("Terakhir dibuat", Modifier.padding(top = 6.dp)) {
                    Text("Lihat semua", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Violet, modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { onNavigate("history") }.padding(4.dp))
                }
            }
            when {
                loading && generations.isEmpty() -> items(3) { ShimmerCard() }
                error != null && generations.isEmpty() -> item { RdCard(Modifier.fillMaxWidth()) { ErrorBox(error, onRefresh) } }
                generations.isEmpty() -> item {
                    RdCard(Modifier.fillMaxWidth()) {
                        EmptyState("Belum ada video", "Ketik satu kata kunci dan studio menyusun naskah, prompt visual & caption.", Icons.Filled.Movie) {
                            GradientButton("Buat video pertama", { onNavigate("generate") }, icon = Icons.Filled.AutoFixHigh)
                        }
                    }
                }
                else -> items(generations.take(5), key = { it.id }) { g -> GenerationCard(g, onOpen) }
            }

            val revisi = generations.filter { it.workflowStatus == "revisi" }
            if (revisi.isNotEmpty()) {
                item { SectionTitle("Perlu revisi", Modifier.padding(top = 6.dp), icon = Icons.Filled.Replay, iconTint = Color(0xFFF43F5E)) }
                items(revisi.take(3), key = { "r" + it.id }) { g ->
                    RdCard(Modifier.fillMaxWidth(), onClick = { onOpen(g) }, padding = 16.dp) {
                        Text(g.keyword, fontWeight = FontWeight.Bold, color = c.ink)
                        Text(g.reviewNote ?: "Reviewer meminta perubahan naskah.", fontSize = 12.sp, color = c.muted, maxLines = 2)
                    }
                }
            }

            item {
                RdCard(Modifier.fillMaxWidth().padding(top = 6.dp)) {
                    Text("Ide untuk dicoba", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                    Spacer(Modifier.height(8.dp))
                    IDEAS.forEach { (kw, desc, model) ->
                        Row(
                            Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).clickable { onIdea(kw, model) }.padding(8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Box(Modifier.size(36.dp).clip(RoundedCornerShape(12.dp)).background(c.surface2), contentAlignment = Alignment.Center) {
                                Icon(Icons.Filled.Lightbulb, null, tint = Violet, modifier = Modifier.size(18.dp))
                            }
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(kw, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = c.ink)
                                Text(desc, fontSize = 12.sp, color = c.muted, maxLines = 1)
                            }
                            Icon(Icons.Filled.NorthEast, null, tint = c.muted, modifier = Modifier.size(16.dp))
                        }
                    }
                }
            }
            item { Spacer(Modifier.height(8.dp)) }
        }
    }
}
