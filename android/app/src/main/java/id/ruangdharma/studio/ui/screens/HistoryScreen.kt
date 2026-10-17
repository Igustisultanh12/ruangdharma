package id.ruangdharma.studio.ui.screens

import android.content.Intent
import android.net.Uri
import android.view.ViewGroup
import android.widget.FrameLayout
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.media3.common.MediaItem
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.ui.PlayerView
import id.ruangdharma.studio.data.model.Counts
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

/** Padanan views/History.vue (tab Semua / Favorit / Arsip + pencarian). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HistoryScreen(
    generations: List<Generation>,
    counts: Counts?,
    tab: Int,
    search: String,
    loading: Boolean,
    refreshing: Boolean,
    error: String?,
    onTab: (Int) -> Unit,
    onSearch: (String) -> Unit,
    onRefresh: () -> Unit,
    onOpen: (Generation) -> Unit,
    onToggleFav: (Generation) -> Unit,
    onCreate: () -> Unit,
) {
    PullToRefreshBox(isRefreshing = refreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                PageHeader("Riwayat saya", "Semua video yang pernah kamu buat. Perubahan dari reviewer muncul otomatis.")
                RdTextField(search, onSearch, placeholder = "Cari tema atau caption…", leading = Icons.Filled.Search, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text))
                Spacer(Modifier.height(12.dp))
                Segmented(
                    listOf("Semua" to Icons.Filled.GridView, "Favorit" to Icons.Filled.Star, "Arsip" to Icons.Filled.Archive),
                    tab, onTab, Modifier.fillMaxWidth(),
                    counts = listOf(counts?.all, counts?.favorites, counts?.archived),
                )
            }
            when {
                loading && generations.isEmpty() -> items(3) { ShimmerCard() }
                error != null && generations.isEmpty() -> item { RdCard(Modifier.fillMaxWidth()) { ErrorBox(error, onRefresh) } }
                generations.isEmpty() -> item {
                    RdCard(Modifier.fillMaxWidth()) {
                        EmptyState(
                            when (tab) { 1 -> "Belum ada favorit"; 2 -> "Arsip kosong"; else -> if (search.isNotBlank()) "Tidak ditemukan" else "Belum ada video" },
                            when (tab) { 1 -> "Tandai bintang pada video yang ingin dipakai lagi."; 2 -> "Video yang diarsipkan muncul di sini."; else -> "Mulai dari satu kata kunci di menu Buat." },
                            if (tab == 1) Icons.Filled.StarBorder else Icons.Filled.Movie,
                        ) { if (tab == 0) GradientButton("Buat video", onCreate, icon = Icons.Filled.AutoFixHigh) }
                    }
                }
                else -> items(generations, key = { it.id }) { g -> GenerationCard(g, onOpen, onToggleFav) }
            }
            item { Spacer(Modifier.height(8.dp)) }
        }
    }
}

/** Padanan components/generation/GenerationModal.vue — layar detail satu video. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun DetailScreen(
    g: Generation,
    onBack: () -> Unit,
    onSaveAudio: (Int, Int, Int?) -> Unit,
    onPublish: (String) -> Unit,
    onToggleFav: (Generation) -> Unit,
    onToast: (String) -> Unit,
) {
    val c = Rd.colors
    val context = LocalContext.current
    val clipboard = LocalClipboardManager.current
    var tab by rememberSaveable(g.id) { mutableIntStateOf(0) }
    var volume by remember(g.id) { mutableFloatStateOf((g.backsoundVolume ?: 20).toFloat()) }
    var trimStart by remember(g.id) { mutableStateOf((g.backsoundTrimStart ?: 0).toString()) }
    var trimEnd by remember(g.id) { mutableStateOf(g.backsoundTrimEnd?.toString() ?: "") }
    val m = modelInfo(g.model)
    val video = g.fullVideoUrl()

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            SecondaryButton("Kembali", onBack, icon = Icons.Filled.ArrowBack, small = true)
            Spacer(Modifier.weight(1f))
            Icon(
                if (g.isFavorite) Icons.Filled.Star else Icons.Filled.StarBorder,
                contentDescription = "Favorit",
                tint = if (g.isFavorite) Color(0xFFEAB308) else c.muted,
                modifier = Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).clickable { onToggleFav(g) }.padding(8.dp),
            )
        }
        Column {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                GenerationBadge(g)
                RdBadge(m.label, m.color, icon = m.icon)
                RdBadge("${g.durasi} dtk", "slate", icon = Icons.Filled.Timer)
            }
            Text(g.keyword, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 28.sp, color = c.ink, modifier = Modifier.padding(top = 10.dp))
            Text("Dibuat ${relativeTime(g.createdAt)}${g.museJobId?.let { " · job Muse #$it" } ?: ""}", fontSize = 12.sp, color = c.muted)
        }

        if (!g.reviewNote.isNullOrBlank() && g.workflowStatus == "revisi") AlertBox("Catatan reviewer: ${g.reviewNote}", color = "rose", icon = Icons.Filled.Replay)

        // Pratinjau
        when {
            video != null -> {
                VideoPlayer(video)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    SecondaryButton("Buka / unduh", {
                        runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(video))) }.onFailure { onToast("Tidak ada aplikasi untuk membuka video.") }
                    }, icon = Icons.Filled.Download, small = true)
                    SecondaryButton("Bagikan", {
                        val send = Intent(Intent.ACTION_SEND).apply { type = "text/plain"; putExtra(Intent.EXTRA_TEXT, g.captionText() + "\n\n" + video) }
                        runCatching { context.startActivity(Intent.createChooser(send, "Bagikan video")) }
                    }, icon = Icons.Filled.Share, small = true)
                }
            }
            g.isPendingMuse -> RdCard(Modifier.fillMaxWidth()) {
                Text("Muse sedang membuat videonya", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                Text("Video, naskah & caption muncul di sini otomatis setelah Muse mengirim hasilnya.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp, bottom = 16.dp))
                CanangLoader(MUSE_STEPS, current = if (g.status == "processing") 2 else 0, modifier = Modifier.fillMaxWidth())
            }
            g.status == "failed" -> AlertBox("Muse gagal membuat video ini. Kirim ulang dari web atau buat video baru.")
            else -> PhonePreview(g)
        }

        Segmented(
            listOf("Naskah" to Icons.Filled.Description, "Caption" to Icons.Filled.Tag, "Audio" to Icons.Filled.MusicNote, "Publish" to Icons.Filled.Send),
            tab, { tab = it }, Modifier.fillMaxWidth(),
        )

        when (tab) {
            0 -> {
                val parts = g.naskah.orEmpty()
                if (parts.isEmpty()) RdCard(Modifier.fillMaxWidth()) { EmptyState("Naskah belum ada", "Naskah muncul setelah Muse selesai.", Icons.Filled.Description) }
                Text("${parts.size} bagian", fontSize = 13.sp, color = c.muted)
                parts.forEachIndexed { i, p ->
                    val sh = RoundedCornerShape(16.dp)
                    Column(Modifier.fillMaxWidth().clip(sh).background(c.card).border(1.dp, c.line, sh).padding(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(28.dp).clip(RoundedCornerShape(9.dp)).background(BrandGradient), contentAlignment = Alignment.Center) {
                                Text("${p.bagian.takeIf { it > 0 } ?: (i + 1)}", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 13.sp)
                            }
                            Spacer(Modifier.width(10.dp))
                            Text(p.speaker ?: "Narator", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = c.ink, modifier = Modifier.weight(1f))
                            p.estimasiDetik?.let { RdBadge("±${it} dtk", "slate", small = true) }
                        }
                        Text(p.text(), fontSize = 14.sp, color = c.ink, lineHeight = 21.sp, modifier = Modifier.padding(top = 10.dp))
                        if (!p.promptVisual.isNullOrBlank()) {
                            Row(Modifier.padding(top = 10.dp).fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(c.surface2).padding(10.dp)) {
                                Icon(Icons.Filled.Image, null, tint = Violet, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(8.dp))
                                Text(p.promptVisual, fontSize = 12.sp, color = c.muted, lineHeight = 17.sp)
                            }
                        }
                    }
                }
            }
            1 -> RdCard(Modifier.fillMaxWidth()) {
                val cap = g.captionText()
                Text(cap.ifBlank { "Caption belum ada." }, fontSize = 14.sp, color = c.ink, lineHeight = 21.sp)
                Spacer(Modifier.height(14.dp))
                BrutalButton("Salin caption", { clipboard.setText(AnnotatedString(cap)); onToast("Caption disalin.") }, icon = Icons.Filled.ContentCopy, small = true, enabled = cap.isNotBlank())
            }
            2 -> RdCard(Modifier.fillMaxWidth()) {
                Text("Editor audio", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                Text("Atur volume & potongan backsound, lalu Muse mencampur ulang videonya.", fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 2.dp, bottom = 12.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Volume backsound", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = c.ink, modifier = Modifier.weight(1f))
                    RdBadge("${volume.toInt()}%", "fuchsia", small = true)
                }
                Slider(volume, { volume = it }, valueRange = 0f..100f, colors = SliderDefaults.colors(thumbColor = Fuchsia, activeTrackColor = Violet, inactiveTrackColor = c.line))
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    Column(Modifier.weight(1f)) { FieldLabel("Mulai (detik)"); RdTextField(trimStart, { trimStart = it.filter(Char::isDigit) }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)) }
                    Column(Modifier.weight(1f)) { FieldLabel("Sampai (detik)"); RdTextField(trimEnd, { trimEnd = it.filter(Char::isDigit) }, placeholder = "habis", keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)) }
                }
                Spacer(Modifier.height(16.dp))
                GradientButton("Simpan & mix ulang", { onSaveAudio(volume.toInt(), trimStart.toIntOrNull() ?: 0, trimEnd.toIntOrNull()) }, icon = Icons.Filled.GraphicEq, modifier = Modifier.fillMaxWidth())
            }
            else -> RdCard(Modifier.fillMaxWidth()) {
                Text("Publish ke medsos", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                Text("Instagram & Facebook diunggah otomatis oleh Muse. TikTok masih manual.", fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 2.dp, bottom = 14.dp))
                if (video == null) AlertBox("Video belum ada file-nya. Tunggu Muse selesai dulu.", Modifier.padding(bottom = 12.dp), color = "amber")
                GradientButton("Publish ke Instagram", { onPublish("instagram") }, icon = Icons.Filled.PhotoCamera, enabled = video != null, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(10.dp))
                BrutalButton("Publish ke Facebook", { onPublish("facebook") }, icon = Icons.Filled.ThumbUp, enabled = video != null, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(4.dp))
                SecondaryButton("TikTok (manual)", {
                    clipboard.setText(AnnotatedString(g.captionText()))
                    onPublish("tiktok")
                }, icon = Icons.Filled.MusicVideo, modifier = Modifier.fillMaxWidth())
            }
        }
        Spacer(Modifier.height(8.dp))
    }
}

/** Pratinjau 9:16 ala PhonePreview.vue saat file video belum ada (mode lokal). */
@Composable
fun PhonePreview(g: Generation) {
    val c = Rd.colors
    val parts = g.naskah.orEmpty()
    var idx by remember(g.id) { mutableIntStateOf(0) }
    val shape = RoundedCornerShape(32.dp)
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        BrutalBox(Modifier.width(230.dp).aspectRatio(9f / 16f).padding(end = 5.dp, bottom = 5.dp), shape = shape, brush = BrandGradient) {
            Box(Modifier.matchParentSize().dotBackground())
            Column(Modifier.fillMaxSize().padding(18.dp)) {
                Text(g.keyword, color = Color.White, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Spacer(Modifier.weight(1f))
                val p = parts.getOrNull(idx)
                if (p != null) {
                    Text(p.speaker ?: "Narator", color = Yellow, fontWeight = FontWeight.ExtraBold, fontSize = 12.sp)
                    Text(p.text(), color = Color.White, fontSize = 13.sp, lineHeight = 18.sp, modifier = Modifier.padding(top = 4.dp))
                }
                Spacer(Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    parts.indices.forEach { i ->
                        Box(Modifier.weight(1f).height(4.dp).clip(RoundedCornerShape(99.dp)).background(if (i <= idx) Color.White else Color.White.copy(alpha = 0.35f)).clickable { idx = i })
                    }
                }
            }
            Box(Modifier.matchParentSize().clickable { if (parts.isNotEmpty()) idx = (idx + 1) % parts.size })
        }
    }
    Text("Ketuk pratinjau untuk lanjut ke bagian berikutnya.", fontSize = 11.sp, color = c.muted, modifier = Modifier.fillMaxWidth(), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
}

/** Pemutar video asli (Media3 ExoPlayer) dengan bingkai brutal 9:16. */
@androidx.annotation.OptIn(markerClass = [androidx.media3.common.util.UnstableApi::class])
@Composable
fun VideoPlayer(url: String) {
    val context = LocalContext.current
    val player = remember(url) {
        ExoPlayer.Builder(context).build().apply {
            setMediaItem(MediaItem.fromUri(url))
            prepare()
            playWhenReady = false
        }
    }
    DisposableEffect(player) { onDispose { player.release() } }
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        BrutalBox(Modifier.fillMaxWidth(0.78f).aspectRatio(9f / 16f).padding(end = 5.dp, bottom = 5.dp), shape = RoundedCornerShape(28.dp), background = Color.Black) {
            AndroidView(
                factory = { ctx ->
                    PlayerView(ctx).apply {
                        layoutParams = FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT)
                        this.player = player
                        useController = true
                    }
                },
                update = { it.player = player },
                modifier = Modifier.fillMaxSize(),
            )
        }
    }
}
