package id.ruangdharma.studio.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.data.model.PulseQuota
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

val SUGGESTIONS = listOf("Rwa Bhineda", "Canang Sari", "Tri Sandhya", "Karma", "Tri Hita Karana", "Bhatara Sri", "Ahimsa", "Nyepi", "Saraswati", "Ngayah")
val MUSE_STEPS = listOf("Muse menerima job", "Muse membuat naskah & caption", "Muse membuat visual (6 klip)", "Muse mengisi suara & subtitle", "Muse mengunggah video ke web")

data class SubmitResult(val message: String, val error: Boolean, val generationId: Int? = null)

data class GenerateRequest(
    val keyword: String, val durasi: Int, val model: String, val viaMuse: Boolean,
    val volume: Int, val trimStart: Int, val trimEnd: Int?,
)

/** Padanan views/Generate.vue (formulir + panel progres Muse). */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun GenerateScreen(
    initialKeyword: String,
    initialModel: String,
    defaultDurasi: Int,
    quota: PulseQuota?,
    submitting: Boolean,
    result: SubmitResult?,
    tracked: Generation?,
    onSubmit: (GenerateRequest) -> Unit,
    onOpen: (Generation) -> Unit,
    onReset: () -> Unit,
) {
    val c = Rd.colors
    var keyword by remember(initialKeyword) { mutableStateOf(initialKeyword) }
    var durasi by remember { mutableIntStateOf(if (defaultDurasi in listOf(30, 60, 90)) defaultDurasi else 60) }
    var model by remember(initialModel) { mutableStateOf(initialModel.ifBlank { "narasi" }) }
    var viaMuse by remember { mutableStateOf(true) }
    var volume by remember { mutableFloatStateOf(20f) }
    var trimStart by remember { mutableStateOf("0") }
    var trimEnd by remember { mutableStateOf("") }
    val quotaEmpty = quota != null && quota.remaining <= 0
    val canSubmit = !submitting && keyword.trim().length > 1 && !quotaEmpty

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        // Panel progres Muse / hasil
        if (result != null) {
            if (result.error) {
                RdCard(Modifier.fillMaxWidth()) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(tone("rose").bg), contentAlignment = Alignment.Center) {
                            Icon(Icons.Filled.ErrorOutline, null, tint = tone("rose").fg)
                        }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(if (quotaEmpty) "Kuota harian habis" else "Video belum berhasil dibuat", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                            Text(result.message, fontSize = 13.sp, color = c.muted)
                        }
                    }
                    Spacer(Modifier.height(12.dp))
                    SecondaryButton("Tutup", onReset, small = true, icon = Icons.Filled.Close)
                }
            } else {
                BrutalCard(Modifier.fillMaxWidth()) {
                    Eyebrow(if (tracked?.status == "completed") "Video siap" else "Diproses Muse", icon = Icons.Filled.Sensors)
                    Text(tracked?.keyword ?: keyword, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 24.sp, color = c.ink, modifier = Modifier.padding(top = 12.dp))
                    Text(result.message, fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp))
                    Spacer(Modifier.height(16.dp))
                    val st = tracked?.status
                    if (st == "failed") {
                        AlertBox("Muse gagal membuat video. Coba kirim ulang dari detail video.")
                    } else {
                        val current = when (st) { "processing" -> 2; "completed" -> 4; else -> 0 }
                        CanangLoader(MUSE_STEPS, current = current, done = st == "completed", modifier = Modifier.fillMaxWidth())
                    }
                    Spacer(Modifier.height(16.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (tracked != null) GradientButton("Lihat detail", { onOpen(tracked) }, icon = Icons.Filled.Visibility)
                        SecondaryButton("Buat lagi", onReset, icon = Icons.Filled.Add)
                    }
                    Text("Status terbarui otomatis tanpa refresh.", fontSize = 11.sp, color = c.muted, modifier = Modifier.padding(top = 10.dp))
                }
            }
        }

        // Formulir
        val shape = RoundedCornerShape(24.dp)
        Column(
            Modifier.fillMaxWidth().clip(shape).background(c.card).border(1.dp, c.line, shape)
                .drawBehind {
                    drawCircle(Brush.linearGradient(BrandColors), radius = 90.dp.toPx(), center = Offset(size.width, 0f), alpha = 0.13f)
                }
                .padding(20.dp),
        ) {
            Text("Buat video baru", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 24.sp, color = c.ink)
            Text("Satu kata kunci jadi naskah 6 bagian, prompt visual, dan caption.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp))
            if (quota != null) QuotaBar(quota.used, quota.limit, Modifier.padding(top = 16.dp))

            Spacer(Modifier.height(20.dp))
            FieldLabel("Tema / kata kunci")
            RdTextField(keyword, { if (it.length <= 100) keyword = it }, placeholder = "mis. Rwa Bhineda", leading = Icons.Filled.AutoAwesome, leadingTint = Violet)
            Spacer(Modifier.height(10.dp))
            FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                SUGGESTIONS.forEach { s -> RdChip(s, keyword == s, { keyword = s }) }
            }

            Spacer(Modifier.height(20.dp))
            FieldLabel("Durasi")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(30 to "Ringkas", 60 to "Standar", 90 to "Lengkap").forEach { (d, label) ->
                    val on = durasi == d
                    val sh = RoundedCornerShape(16.dp)
                    Column(
                        Modifier.weight(1f).padding(end = 3.dp, bottom = 3.dp)
                            .then(if (on) Modifier.brutalShadow(sh, c.shadow, 3.dp) else Modifier)
                            .clip(sh).background(if (on) Yellow else c.card)
                            .border(2.dp, if (on) c.ink else c.line, sh)
                            .clickable { durasi = d }.padding(vertical = 12.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Row(verticalAlignment = Alignment.Bottom) {
                            Text("$d", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 20.sp, color = if (on) Zinc950 else c.ink)
                            Text("dtk", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = if (on) Zinc950 else c.ink, modifier = Modifier.padding(bottom = 3.dp))
                        }
                        Text(label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = (if (on) Zinc950 else c.ink).copy(alpha = 0.75f))
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            FieldLabel("Gaya penyampaian")
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                MODELS.forEach { m ->
                    val on = model == m.value
                    val sh = RoundedCornerShape(16.dp)
                    Row(
                        Modifier.fillMaxWidth().padding(end = 3.dp, bottom = 3.dp)
                            .then(if (on) Modifier.brutalShadow(sh, c.shadow, 3.dp) else Modifier)
                            .clip(sh).background(c.card).border(2.dp, if (on) c.ink else c.line, sh)
                            .clickable { model = m.value }.padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(
                            Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).then(if (on) Modifier.background(BrandGradient) else Modifier.background(c.surface2)),
                            contentAlignment = Alignment.Center,
                        ) { Icon(m.icon, null, tint = if (on) Color.White else c.muted, modifier = Modifier.size(20.dp)) }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(m.label, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = c.ink)
                            Text(m.desc, fontSize = 12.sp, color = c.muted)
                        }
                        if (on) Icon(Icons.Filled.CheckCircle, null, tint = Violet, modifier = Modifier.size(20.dp))
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(c.surface2).clickable { viaMuse = !viaMuse }.padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("Buat video beneran via Muse", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = c.ink)
                    Text("Muse membuat visual, suara & subtitle lalu mengunggah videonya ke sini.", fontSize = 12.sp, color = c.muted)
                }
                Spacer(Modifier.width(8.dp))
                Switch(
                    checked = viaMuse, onCheckedChange = { viaMuse = it },
                    colors = SwitchDefaults.colors(checkedTrackColor = Violet, checkedThumbColor = Color.White, uncheckedTrackColor = c.line, uncheckedBorderColor = c.line),
                )
            }

            Spacer(Modifier.height(20.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.MusicNote, null, tint = Fuchsia, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
                Text("Backsound", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = c.ink)
                Spacer(Modifier.weight(1f))
                RdBadge("Volume ${volume.toInt()}%", "fuchsia", small = true)
            }
            Slider(
                volume, { volume = it }, valueRange = 0f..100f,
                colors = SliderDefaults.colors(thumbColor = Fuchsia, activeTrackColor = Violet, inactiveTrackColor = c.line),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Column(Modifier.weight(1f)) {
                    FieldLabel("Mulai (detik)")
                    RdTextField(trimStart, { trimStart = it.filter(Char::isDigit) }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number))
                }
                Column(Modifier.weight(1f)) {
                    FieldLabel("Sampai (detik)")
                    RdTextField(trimEnd, { trimEnd = it.filter(Char::isDigit) }, placeholder = "habis", keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number))
                }
            }
            Text("Backsound dipotong / di-loop otomatis agar pas durasi video, dicampur pelan di bawah narasi.", fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))

            AnimatedVisibility(quotaEmpty) { AlertBox("Kuota harian kamu sudah habis. Coba lagi besok atau minta admin menaikkan paket.", Modifier.padding(top = 16.dp), color = "amber") }

            Spacer(Modifier.height(20.dp))
            GradientButton(
                text = if (submitting) "Mengirim…" else if (viaMuse) "Kirim ke Muse" else "Buat video",
                onClick = { onSubmit(GenerateRequest(keyword.trim(), durasi, model, viaMuse, volume.toInt(), trimStart.toIntOrNull() ?: 0, trimEnd.toIntOrNull())) },
                icon = Icons.Filled.AutoFixHigh, enabled = canSubmit, loading = submitting, large = true,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        Spacer(Modifier.height(8.dp))
    }
}
