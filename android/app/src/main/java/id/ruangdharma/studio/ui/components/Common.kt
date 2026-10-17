package id.ruangdharma.studio.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawOutline
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.ui.theme.*

/* ============================================================
 * Kit komponen — padanan 1:1 dari kelas CSS web:
 * .card, .card-brutal, .btn-primary, .btn-brutal, .btn-secondary,
 * .eyebrow, .chip(.chip-active), .segment, Badge, StatCard, dst.
 * ============================================================ */

/** Bayangan "brutal": blok tinta solid bergeser (5px 5px 0 ink). */
fun Modifier.brutalShadow(shape: Shape, color: Color, offset: Dp = 5.dp): Modifier = this.drawBehind {
    val o = offset.toPx()
    val outline = shape.createOutline(size, layoutDirection, this)
    translate(o, o) { drawOutline(outline, color) }
}

@Composable
fun BrutalBox(
    modifier: Modifier = Modifier,
    shape: Shape = RoundedCornerShape(24.dp),
    background: Color = Rd.colors.card,
    brush: Brush? = null,
    offset: Dp = 5.dp,
    content: @Composable BoxScope.() -> Unit,
) {
    val c = Rd.colors
    val bg = if (brush != null) Modifier.background(brush, shape) else Modifier.background(background, shape)
    Box(
        modifier
            .brutalShadow(shape, c.shadow, offset)
            .then(bg)
            .border(2.dp, c.ink, shape)
            .clip(shape),
        content = content,
    )
}

/** .card — rounded-3xl, border line, bg card. */
@Composable
fun RdCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    padding: Dp = 20.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val c = Rd.colors
    val shape = RoundedCornerShape(24.dp)
    Column(
        modifier
            .clip(shape)
            .background(c.card)
            .border(1.dp, c.line, shape)
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(padding),
        content = content,
    )
}

/** .card-brutal — border tinta 2dp + bayangan offset. */
@Composable
fun BrutalCard(modifier: Modifier = Modifier, padding: Dp = 20.dp, content: @Composable ColumnScope.() -> Unit) {
    BrutalBox(modifier.padding(end = 5.dp, bottom = 5.dp)) {
        Column(Modifier.fillMaxWidth().padding(padding), content = content)
    }
}

/* ---------------- Logo canang ---------------- */

private val PetalColors = listOf(Color(0xFFF8F5EE), Color(0xFFEF4444), Color(0xFFFACC15), Color(0xFF3B82F6))
private val PetalPos = listOf(Offset(32f, 15f), Offset(49f, 32f), Offset(32f, 49f), Offset(15f, 32f))

/**
 * Canang sari dari atas: putih (timur), merah (selatan), kuning (barat), biru (utara)
 * + kembang rampai bergradasi di tengah. Persis seperti CanangMark.vue (viewBox 64).
 */
@Composable
fun CanangMark(
    modifier: Modifier = Modifier,
    size: Int = 40,
    lit: Int = 5,
    active: Int = -1,
    spin: Boolean = false,
    stroke: Color = Color(0xFF15101F),
    frame: Color = Rd.colors.ink,
) {
    val infinite = rememberInfiniteTransition(label = "canang")
    val angle by infinite.animateFloat(0f, 360f, infiniteRepeatable(tween(14000, easing = LinearEasing)), label = "spin")
    val pulse by infinite.animateFloat(1f, 1.18f, infiniteRepeatable(tween(650), RepeatMode.Reverse), label = "pulse")
    Canvas(modifier.size(size.dp).then(if (spin) Modifier.rotate(angle) else Modifier)) {
        val s = this.size.minDimension / 64f
        val sw = 2.6f * s
        // bingkai belah ketupat tipis
        rotate(45f, pivot = center) {
            drawRoundRect(
                color = frame.copy(alpha = 0.18f), topLeft = Offset(6f * s, 6f * s), size = Size(52f * s, 52f * s),
                cornerRadius = CornerRadius(12f * s), style = Stroke(2f * s),
            )
        }
        PetalPos.forEachIndexed { i, p ->
            val rx = (if (i % 2 == 0) 8f else 11f) * s
            val ry = (if (i % 2 == 0) 11f else 8f) * s
            val k = if (active == i) pulse else 1f
            val c = Offset(p.x * s, p.y * s)
            val tl = Offset(c.x - rx * k, c.y - ry * k)
            val sz = Size(2 * rx * k, 2 * ry * k)
            if (i < lit) drawOval(PetalColors[i], tl, sz)
            drawOval(stroke, tl, sz, style = Stroke(sw))
        }
        val r = 8.5f * s * (if (active == 4) pulse else 1f)
        val ctr = Offset(32f * s, 32f * s)
        if (lit >= 5) drawCircle(Brush.linearGradient(BrandColors, Offset(ctr.x - r, ctr.y - r), Offset(ctr.x + r, ctr.y + r)), r, ctr)
        drawCircle(stroke, r, ctr, style = Stroke(sw))
        if (lit >= 5) drawCircle(Color.White.copy(alpha = 0.85f), 2f * s, Offset(29.5f * s, 29.5f * s))
    }
}

/** Logo.vue — kotak kuning brutal + CanangMark + wordmark. */
@Composable
fun RdLogo(modifier: Modifier = Modifier, light: Boolean = false, compact: Boolean = false) {
    Row(modifier, verticalAlignment = Alignment.CenterVertically) {
        BrutalBox(
            Modifier.size(40.dp), shape = RoundedCornerShape(12.dp),
            background = if (light) Color.White else Yellow, offset = 3.dp,
        ) { CanangMark(Modifier.align(Alignment.Center), size = 30, frame = Color(0xFF15101F)) }
        if (!compact) {
            Spacer(Modifier.width(12.dp))
            Column {
                Text("Ruang Dharma", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 17.sp, color = if (light) Color.White else Rd.colors.ink, lineHeight = 18.sp)
                Text("Studio video cerita Hindu", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = if (light) Color.White.copy(alpha = 0.75f) else Rd.colors.muted, lineHeight = 13.sp)
            }
        }
    }
}

/* ---------------- Tombol ---------------- */

@Composable
private fun pressScale(source: MutableInteractionSource): Float {
    val pressed by source.collectIsPressedAsState()
    val s by animateFloatAsState(if (pressed) 0.97f else 1f, label = "press")
    return s
}

/** .btn-primary — gradasi brand animasi + glow violet. */
@Composable
fun GradientButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    enabled: Boolean = true,
    loading: Boolean = false,
    large: Boolean = false,
) {
    val src = remember { MutableInteractionSource() }
    val infinite = rememberInfiniteTransition(label = "grad")
    val shift by infinite.animateFloat(0f, 1f, infiniteRepeatable(tween(6000), RepeatMode.Reverse), label = "shift")
    val shape = RoundedCornerShape(if (large) 18.dp else 16.dp)
    Box(
        modifier
            .scale(pressScale(src))
            .alpha(if (enabled) 1f else 0.55f)
            .drawBehind {
                drawRoundRect(Violet.copy(alpha = 0.28f), topLeft = Offset(8f, 18f), size = Size(size.width - 16f, size.height - 6f), cornerRadius = CornerRadius(40f))
            }
            .clip(shape)
            .background(Brush.linearGradient(BrandColors, start = Offset(-400f * shift, 0f), end = Offset(900f + 400f * (1 - shift), 300f)))
            .clickable(interactionSource = src, indication = rdIndication(), enabled = enabled && !loading, onClick = onClick)
            .padding(horizontal = 20.dp, vertical = if (large) 16.dp else 12.dp),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
            if (loading) CircularProgressIndicator(Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
            else if (icon != null) Icon(icon, null, tint = Color.White, modifier = Modifier.size(if (large) 20.dp else 18.dp))
            if (loading || icon != null) Spacer(Modifier.width(8.dp))
            Text(text, color = Color.White, fontWeight = FontWeight.Bold, fontSize = if (large) 16.sp else 14.sp)
        }
    }
}

@Composable
private fun rdIndication() = androidx.compose.foundation.LocalIndication.current

/** .btn-brutal — kuning + border tinta + bayangan offset kecil. */
@Composable
fun BrutalButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    color: Color = Yellow,
    small: Boolean = false,
    enabled: Boolean = true,
) {
    val src = remember { MutableInteractionSource() }
    val pressed by src.collectIsPressedAsState()
    val shape = RoundedCornerShape(if (small) 12.dp else 16.dp)
    val off = if (pressed) 0.dp else 3.dp
    Box(
        modifier
            .padding(end = 3.dp, bottom = 3.dp)
            .offset(x = 3.dp - off, y = 3.dp - off)
            .alpha(if (enabled) 1f else 0.55f)
            .brutalShadow(shape, Color(0xFF15101F), off)
            .clip(shape)
            .background(color)
            .border(2.dp, Color(0xFF15101F), shape)
            .clickable(interactionSource = src, indication = rdIndication(), enabled = enabled, onClick = onClick)
            .padding(horizontal = if (small) 12.dp else 18.dp, vertical = if (small) 7.dp else 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (icon != null) { Icon(icon, null, tint = Zinc950, modifier = Modifier.size(if (small) 16.dp else 18.dp)); Spacer(Modifier.width(6.dp)) }
            Text(text, color = Zinc950, fontWeight = FontWeight.Bold, fontSize = if (small) 12.sp else 14.sp)
        }
    }
}

/** .btn-secondary — border-2 line, bg card. */
@Composable
fun SecondaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    small: Boolean = false,
    enabled: Boolean = true,
    danger: Boolean = false,
) {
    val c = Rd.colors
    val src = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(if (small) 12.dp else 16.dp)
    val fg = if (danger) Color(0xFFE11D48) else c.ink
    Box(
        modifier
            .scale(pressScale(src))
            .alpha(if (enabled) 1f else 0.55f)
            .clip(shape)
            .background(c.card)
            .border(2.dp, c.line, shape)
            .clickable(interactionSource = src, indication = rdIndication(), enabled = enabled, onClick = onClick)
            .padding(horizontal = if (small) 12.dp else 18.dp, vertical = if (small) 7.dp else 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (icon != null) { Icon(icon, null, tint = fg, modifier = Modifier.size(if (small) 16.dp else 18.dp)); Spacer(Modifier.width(6.dp)) }
            Text(text, color = fg, fontWeight = FontWeight.Bold, fontSize = if (small) 12.sp else 14.sp)
        }
    }
}

/** Tombol transparan di atas hero gradasi (border putih 60%). */
@Composable
fun GlassButton(text: String, onClick: () -> Unit, icon: ImageVector? = null, modifier: Modifier = Modifier) {
    val shape = RoundedCornerShape(12.dp)
    Row(
        modifier.clip(shape).background(Color.White.copy(alpha = 0.15f)).border(2.dp, Color.White.copy(alpha = 0.6f), shape)
            .clickable(onClick = onClick).padding(horizontal = 12.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon != null) { Icon(icon, null, tint = Color.White, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(6.dp)) }
        Text(text, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 12.sp)
    }
}

/* ---------------- Label, chip, badge ---------------- */

/** .eyebrow — pil kuning brutal kecil. */
@Composable
fun Eyebrow(text: String, icon: ImageVector? = Icons.Filled.AutoAwesome, modifier: Modifier = Modifier) {
    BrutalBox(modifier.padding(end = 3.dp, bottom = 3.dp), shape = RoundedCornerShape(999.dp), background = Yellow, offset = 3.dp) {
        Row(Modifier.padding(horizontal = 12.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
            if (icon != null) { Icon(icon, null, tint = Color(0xFF18181B), modifier = Modifier.size(14.dp)); Spacer(Modifier.width(6.dp)) }
            Text(text, color = Color(0xFF18181B), fontWeight = FontWeight.ExtraBold, fontSize = 12.sp, letterSpacing = 0.3.sp)
        }
    }
}

/** .chip / .chip-active */
@Composable
fun RdChip(text: String, selected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier, icon: ImageVector? = null) {
    val c = Rd.colors
    val shape = RoundedCornerShape(999.dp)
    val content: @Composable () -> Unit = {
        Row(Modifier.padding(horizontal = 12.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            if (icon != null) { Icon(icon, null, tint = if (selected) Zinc950 else c.muted, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(6.dp)) }
            Text(text, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (selected) Zinc950 else c.muted)
        }
    }
    if (selected) {
        Box(modifier.padding(end = 3.dp, bottom = 3.dp).brutalShadow(shape, c.shadow, 3.dp).clip(shape).background(Yellow).border(2.dp, if (c.dark) Yellow else c.ink, shape).clickable(onClick = onClick)) { content() }
    } else {
        Box(modifier.padding(end = 3.dp, bottom = 3.dp).clip(shape).background(c.card).border(2.dp, c.line, shape).clickable(onClick = onClick)) { content() }
    }
}

data class Tone(val bg: Color, val fg: Color, val ring: Color, val dot: Color)

@Composable
fun tone(name: String): Tone {
    val d = Rd.colors.dark
    fun t(l100: Long, l700: Long, l200: Long, b500: Long, d300: Long) =
        if (d) Tone(Color(b500).copy(alpha = 0.15f), Color(d300), Color(b500).copy(alpha = 0.3f), Color(b500))
        else Tone(Color(l100), Color(l700), Color(l200), Color(b500))
    return when (name) {
        "zinc" -> t(0xFFF4F4F5, 0xFF52525B, 0xFFE4E4E7, 0xFF71717A, 0xFFD4D4D8)
        "violet" -> t(0xFFEDE9FE, 0xFF6D28D9, 0xFFDDD6FE, 0xFF8B5CF6, 0xFFC4B5FD)
        "fuchsia" -> t(0xFFFAE8FF, 0xFFA21CAF, 0xFFF5D0FE, 0xFFD946EF, 0xFFF0ABFC)
        "sky" -> t(0xFFE0F2FE, 0xFF0369A1, 0xFFBAE6FD, 0xFF0EA5E9, 0xFF7DD3FC)
        "orange" -> t(0xFFFFEDD5, 0xFFC2410C, 0xFFFED7AA, 0xFFF97316, 0xFFFDBA74)
        "amber" -> t(0xFFFEF3C7, 0xFF92400E, 0xFFFDE68A, 0xFFF59E0B, 0xFFFCD34D)
        "rose" -> t(0xFFFFE4E6, 0xFFBE123C, 0xFFFECDD3, 0xFFF43F5E, 0xFFFDA4AF)
        "emerald" -> t(0xFFD1FAE5, 0xFF047857, 0xFFA7F3D0, 0xFF10B981, 0xFF6EE7B7)
        "pink" -> t(0xFFFCE7F3, 0xFFBE185D, 0xFFFBCFE8, 0xFFEC4899, 0xFFF9A8D4)
        else -> t(0xFFF1F5F9, 0xFF334155, 0xFFE2E8F0, 0xFF94A3B8, 0xFFCBD5E1)
    }
}

/** Badge.vue — pil berwarna dengan ring tipis. */
@Composable
fun RdBadge(text: String, color: String = "slate", icon: ImageVector? = null, dot: Boolean = false, small: Boolean = false) {
    val t = tone(color)
    val shape = RoundedCornerShape(999.dp)
    Row(
        Modifier.clip(shape).background(t.bg).border(1.dp, t.ring, shape)
            .padding(horizontal = if (small) 8.dp else 10.dp, vertical = if (small) 2.dp else 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (dot) { Box(Modifier.size(6.dp).clip(CircleShape).background(t.dot)); Spacer(Modifier.width(6.dp)) }
        if (icon != null) { Icon(icon, null, tint = t.fg, modifier = Modifier.size(if (small) 12.dp else 14.dp)); Spacer(Modifier.width(5.dp)) }
        Text(text, color = t.fg, fontWeight = FontWeight.Bold, fontSize = if (small) 10.sp else 12.sp, maxLines = 1)
    }
}

/* Status proses Muse + alur review — sama dengan GEN_STATUS & WORKFLOW di web. */
data class StatusInfo(val label: String, val color: String, val icon: ImageVector)

fun genStatusInfo(status: String): StatusInfo? = when (status) {
    "waiting_muse" -> StatusInfo("Menunggu Muse", "sky", Icons.Filled.HourglassTop)
    "processing" -> StatusInfo("Muse memproses", "violet", Icons.Filled.Autorenew)
    "failed" -> StatusInfo("Muse gagal", "rose", Icons.Filled.Cancel)
    else -> null
}

fun workflowInfo(w: String?): StatusInfo = when (w) {
    "review" -> StatusInfo("Menunggu review", "amber", Icons.Filled.HourglassTop)
    "revisi" -> StatusInfo("Perlu revisi", "rose", Icons.Filled.Replay)
    "approved" -> StatusInfo("Disetujui", "emerald", Icons.Filled.Verified)
    "published" -> StatusInfo("Terbit", "fuchsia", Icons.Filled.Send)
    "archived" -> StatusInfo("Diarsipkan", "zinc", Icons.Filled.Archive)
    else -> StatusInfo("Draf", "slate", Icons.Filled.Edit)
}

data class ModelInfo(val value: String, val label: String, val desc: String, val color: String, val icon: ImageVector)

val MODELS = listOf(
    ModelInfo("narasi", "Narasi", "Satu narator, visual diam + voiceover", "violet", Icons.Filled.Mic),
    ModelInfo("ngobrol", "Ngobrol", "Dialog Ibu (Aria) & Anak (Lumi)", "sky", Icons.Filled.Forum),
    ModelInfo("gabungan", "Gabungan", "Narator + dialog Ibu & Anak", "orange", Icons.Filled.AutoAwesome),
)
fun modelInfo(v: String): ModelInfo = MODELS.firstOrNull { it.value == v } ?: ModelInfo(v, v, "", "slate", Icons.Filled.Movie)

/** Badge status untuk sebuah video: status Muse bila sedang/ gagal diproses, selain itu tahap review. */
@Composable
fun GenerationBadge(g: Generation, small: Boolean = false) {
    val s = genStatusInfo(g.status)
    if (s != null) RdBadge(s.label, s.color, icon = s.icon, small = small)
    else { val w = workflowInfo(g.workflowStatus); RdBadge(w.label, w.color, dot = true, small = small) }
}

@Composable
fun StatusBadge(status: String) {
    val s = genStatusInfo(status)
    if (s != null) RdBadge(s.label, s.color, icon = s.icon) else RdBadge(if (status == "completed") "Selesai" else status, "emerald", dot = true)
}

/* ---------------- Segmented (.segment) ---------------- */

@Composable
fun Segmented(options: List<Pair<String, ImageVector?>>, selected: Int, onSelect: (Int) -> Unit, modifier: Modifier = Modifier, counts: List<Int?>? = null) {
    val c = Rd.colors
    val shape = RoundedCornerShape(16.dp)
    Row(modifier.clip(shape).background(c.surface2).border(1.dp, c.line, shape).padding(4.dp)) {
        options.forEachIndexed { i, (label, icon) ->
            val on = i == selected
            val itemShape = RoundedCornerShape(12.dp)
            Row(
                Modifier.weight(1f).clip(itemShape)
                    .then(if (on) Modifier.background(c.card).border(1.dp, c.line, itemShape) else Modifier)
                    .clickable { onSelect(i) }
                    .padding(horizontal = 8.dp, vertical = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.Center,
            ) {
                if (icon != null) { Icon(icon, null, tint = if (on) c.ink else c.muted, modifier = Modifier.size(15.dp)); Spacer(Modifier.width(5.dp)) }
                Text(label, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (on) c.ink else c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                val n = counts?.getOrNull(i)
                if (n != null) {
                    Spacer(Modifier.width(5.dp))
                    Box(Modifier.clip(RoundedCornerShape(999.dp)).background(if (on) Yellow else c.line).padding(horizontal = 6.dp, vertical = 1.dp)) {
                        Text(n.toString(), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = if (on) Zinc950 else c.muted)
                    }
                }
            }
        }
    }
}

/* ---------------- Stat card ---------------- */

fun gradientFor(color: String): List<Color> = when (color) {
    "fuchsia" -> listOf(Color(0xFFD946EF), Color(0xFFEC4899))
    "sky" -> listOf(Color(0xFF38BDF8), Color(0xFF6366F1))
    "orange" -> listOf(Color(0xFFFB923C), Color(0xFFF43F5E))
    "amber" -> listOf(Color(0xFFFBBF24), Color(0xFFF97316))
    "emerald" -> listOf(Color(0xFF34D399), Color(0xFF14B8A6))
    "rose" -> listOf(Color(0xFFFB7185), Color(0xFFD946EF))
    "lime" -> listOf(Color(0xFFA3E635), Color(0xFF10B981))
    else -> listOf(Color(0xFF8B5CF6), Color(0xFFD946EF))
}

@Composable
fun StatCard(label: String, value: String, icon: ImageVector, color: String = "violet", hint: String = "", loading: Boolean = false, modifier: Modifier = Modifier, onClick: (() -> Unit)? = null) {
    val c = Rd.colors
    val g = gradientFor(color)
    val shape = RoundedCornerShape(24.dp)
    Box(
        modifier.clip(shape).background(c.card).border(1.dp, c.line, shape)
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .drawBehind {
                drawCircle(Brush.linearGradient(g), radius = 56.dp.toPx(), center = Offset(size.width - 16.dp.toPx(), 4.dp.toPx()), alpha = 0.15f)
            }
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text(label, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (loading) Skeleton(Modifier.padding(top = 8.dp).width(64.dp).height(28.dp))
                else Text(value, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 28.sp, color = c.ink, modifier = Modifier.padding(top = 4.dp))
                if (hint.isNotBlank()) Text(hint, fontSize = 11.sp, color = c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 2.dp))
            }
            Box(Modifier.size(40.dp).clip(RoundedCornerShape(14.dp)).background(Brush.linearGradient(g)), contentAlignment = Alignment.Center) {
                Icon(icon, null, tint = Color.White, modifier = Modifier.size(20.dp))
            }
        }
    }
}

/** Bar kuota bergradasi seperti di sidebar web. */
@Composable
fun QuotaBar(used: Int, limit: Int, modifier: Modifier = Modifier, label: String = "Kuota hari ini") {
    val c = Rd.colors
    val pct = if (limit > 0) (used.toFloat() / limit).coerceIn(0f, 1f) else 0f
    val anim by animateFloatAsState(pct, tween(700), label = "quota")
    Column(modifier) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(label, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = c.muted)
            Text("$used / $limit", fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = c.ink)
        }
        Spacer(Modifier.height(6.dp))
        Box(Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(999.dp)).background(c.surface2)) {
            Box(Modifier.fillMaxWidth(anim).fillMaxHeight().clip(RoundedCornerShape(999.dp)).background(if (pct >= 0.9f) Brush.linearGradient(listOf(Color(0xFFF43F5E), Orange)) else BrandGradient))
        }
    }
}

/* ---------------- Teks & form ---------------- */

@Composable
fun PageHeader(title: String, description: String = "", modifier: Modifier = Modifier, actions: @Composable RowScope.() -> Unit = {}) {
    val c = Rd.colors
    Row(modifier.fillMaxWidth().padding(bottom = 16.dp), verticalAlignment = Alignment.Bottom) {
        Column(Modifier.weight(1f)) {
            Text(title, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 26.sp, color = c.ink, lineHeight = 30.sp)
            if (description.isNotBlank()) Text(description, fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp), lineHeight = 18.sp)
        }
        Row(verticalAlignment = Alignment.CenterVertically, content = actions)
    }
}

@Composable
fun SectionTitle(text: String, modifier: Modifier = Modifier, icon: ImageVector? = null, iconTint: Color = Violet, trailing: @Composable RowScope.() -> Unit = {}) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        if (icon != null) { Icon(icon, null, tint = iconTint, modifier = Modifier.size(20.dp)); Spacer(Modifier.width(8.dp)) }
        Text(text, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = Rd.colors.ink, modifier = Modifier.weight(1f))
        trailing()
    }
}

@Composable
fun FieldLabel(text: String, modifier: Modifier = Modifier) {
    Text(text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Rd.colors.ink, modifier = modifier.padding(bottom = 6.dp))
}

/** .input — rounded-2xl, border-2 line, fokus violet + ring. */
@Composable
fun RdTextField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "",
    leading: ImageVector? = null,
    leadingTint: Color? = null,
    error: Boolean = false,
    password: Boolean = false,
    keyboardOptions: KeyboardOptions = KeyboardOptions.Default,
    singleLine: Boolean = true,
    minLines: Int = 1,
    enabled: Boolean = true,
) {
    val c = Rd.colors
    var focused by remember { mutableStateOf(false) }
    var show by remember { mutableStateOf(false) }
    val shape = RoundedCornerShape(16.dp)
    val borderColor = when { error -> Color(0xFFFB7185); focused -> Violet; else -> c.line }
    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        enabled = enabled,
        singleLine = singleLine,
        minLines = minLines,
        keyboardOptions = keyboardOptions,
        visualTransformation = if (password && !show) PasswordVisualTransformation() else VisualTransformation.None,
        textStyle = MaterialTheme.typography.bodyMedium.copy(color = c.ink, fontSize = 15.sp),
        cursorBrush = SolidColor(Violet),
        modifier = modifier
            .fillMaxWidth()
            .then(if (focused) Modifier.border(4.dp, (if (error) Color(0xFFF43F5E) else Violet).copy(alpha = 0.15f), RoundedCornerShape(20.dp)).padding(2.dp) else Modifier.padding(2.dp))
            .onFocusChangedCompat { focused = it },
        decorationBox = { inner ->
            Row(
                Modifier.clip(shape).background(c.card).border(2.dp, borderColor, shape).padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = if (singleLine) Alignment.CenterVertically else Alignment.Top,
            ) {
                if (leading != null) { Icon(leading, null, tint = leadingTint ?: c.muted, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(10.dp)) }
                Box(Modifier.weight(1f)) {
                    if (value.isEmpty()) Text(placeholder, color = c.muted.copy(alpha = 0.7f), fontSize = 15.sp)
                    inner()
                }
                if (password) {
                    Icon(
                        if (show) Icons.Filled.VisibilityOff else Icons.Filled.Visibility,
                        contentDescription = if (show) "Sembunyikan password" else "Tampilkan password",
                        tint = c.muted,
                        modifier = Modifier.size(20.dp).clip(CircleShape).clickable { show = !show },
                    )
                }
            }
        },
    )
}

private fun Modifier.onFocusChangedCompat(cb: (Boolean) -> Unit): Modifier =
    this.onFocusChanged { cb(it.isFocused) }

/* ---------------- Loader & state ---------------- */

@Composable
fun Skeleton(modifier: Modifier = Modifier) {
    val infinite = rememberInfiniteTransition(label = "sk")
    val a by infinite.animateFloat(0.45f, 1f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "a")
    Box(modifier.clip(RoundedCornerShape(10.dp)).background(Rd.colors.surface2.copy(alpha = a)))
}

@Composable
fun ShimmerCard(modifier: Modifier = Modifier) {
    RdCard(modifier.fillMaxWidth()) {
        Skeleton(Modifier.width(110.dp).height(20.dp))
        Spacer(Modifier.height(12.dp))
        Skeleton(Modifier.fillMaxWidth(0.7f).height(18.dp))
        Spacer(Modifier.height(8.dp))
        Skeleton(Modifier.fillMaxWidth().height(40.dp))
    }
}

/** CanangLoader.vue — tiap tahap menyalakan satu kelopak canang. */
@Composable
fun CanangLoader(steps: List<String>, current: Int, done: Boolean = false, modifier: Modifier = Modifier) {
    val c = Rd.colors
    val petalNames = listOf("Putih · timur", "Merah · selatan", "Kuning · barat", "Biru · utara", "Rampai · tengah")
    val infinite = rememberInfiniteTransition(label = "ring")
    val ang by infinite.animateFloat(0f, 360f, infiniteRepeatable(tween(14000, easing = LinearEasing)), label = "ring")
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.size(160.dp).clip(CircleShape).background(c.surface2), contentAlignment = Alignment.Center) {
            Canvas(Modifier.fillMaxSize().padding(12.dp).rotate(ang)) {
                drawCircle(c.line, style = Stroke(2.dp.toPx(), pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(14f, 10f))))
            }
            CanangMark(size = 120, lit = if (done) 5 else current + 1, active = if (done) -1 else current, stroke = c.ink)
        }
        Spacer(Modifier.height(20.dp))
        steps.forEachIndexed { i, s ->
            val passed = done || i < current
            val now = !done && i == current
            Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                val shape = RoundedCornerShape(8.dp)
                Box(
                    Modifier.size(28.dp).clip(shape)
                        .background(if (passed) Color(0xFF34D399) else if (now) Yellow else Color.Transparent)
                        .border(2.dp, if (passed || now) c.ink else c.line, shape),
                    contentAlignment = Alignment.Center,
                ) {
                    if (passed) Icon(Icons.Filled.Check, null, tint = Zinc950, modifier = Modifier.size(16.dp))
                    else Text("${i + 1}", fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = if (now) Zinc950 else c.muted)
                }
                Spacer(Modifier.width(12.dp))
                Text(s, fontSize = 14.sp, fontWeight = if (passed || now) FontWeight.Bold else FontWeight.Normal, color = if (passed || now) c.ink else c.muted, modifier = Modifier.weight(1f))
                Text(petalNames.getOrElse(i) { "" }, fontSize = 11.sp, color = c.muted)
            }
        }
    }
}

@Composable
fun EmptyState(title: String, description: String = "", icon: ImageVector = Icons.Filled.Inbox, modifier: Modifier = Modifier, action: (@Composable () -> Unit)? = null) {
    val c = Rd.colors
    Column(modifier.fillMaxWidth().padding(vertical = 32.dp, horizontal = 16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.size(64.dp).clip(RoundedCornerShape(20.dp)).background(c.surface2), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = Violet, modifier = Modifier.size(30.dp))
        }
        Spacer(Modifier.height(14.dp))
        Text(title, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
        if (description.isNotBlank()) Text(description, fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
        if (action != null) { Spacer(Modifier.height(16.dp)); action() }
    }
}

/** Kotak peringatan merah muda seperti alert di web. */
@Composable
fun AlertBox(message: String, modifier: Modifier = Modifier, color: String = "rose", icon: ImageVector = Icons.Filled.ErrorOutline) {
    val t = tone(color)
    val shape = RoundedCornerShape(16.dp)
    Row(modifier.fillMaxWidth().clip(shape).background(t.bg).border(2.dp, t.ring, shape).padding(horizontal = 14.dp, vertical = 12.dp), verticalAlignment = Alignment.Top) {
        Icon(icon, null, tint = t.fg, modifier = Modifier.size(18.dp).padding(top = 1.dp))
        Spacer(Modifier.width(10.dp))
        Text(message, color = t.fg, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
fun ErrorBox(message: String, onRetry: () -> Unit) {
    EmptyState(title = "Ups, gagal memuat", description = message, icon = Icons.Filled.CloudOff) {
        BrutalButton("Coba lagi", onRetry, icon = Icons.Filled.Refresh, small = true)
    }
}

/** Dekorasi titik-titik (.dot-bg) untuk hero gradasi. */
fun Modifier.dotBackground(color: Color = Color.White.copy(alpha = 0.22f), gap: Dp = 18.dp): Modifier = this.drawBehind {
    val g = gap.toPx()
    var y = g / 2
    while (y < size.height) {
        var x = g / 2
        while (x < size.width) { drawCircle(color, 1.4f.dp.toPx(), Offset(x, y)); x += g }
        y += g
    }
}

@Composable
fun Avatar(name: String, size: Dp = 44.dp, modifier: Modifier = Modifier) {
    val initials = name.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }.ifBlank { "RD" }
    Box(modifier.size(size).clip(RoundedCornerShape(size / 3)).background(BrandGradient), contentAlignment = Alignment.Center) {
        Text(initials, color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = (size.value * 0.36f).sp, fontFamily = SpaceGrotesk)
    }
}

/** Kartu video untuk daftar (sama dengan kartu grid Riwayat di web). */
@Composable
fun GenerationCard(g: Generation, onOpen: (Generation) -> Unit, onToggleFav: ((Generation) -> Unit)? = null, modifier: Modifier = Modifier) {
    val c = Rd.colors
    val m = modelInfo(g.model)
    RdCard(modifier.fillMaxWidth(), onClick = { onOpen(g) }) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
            Box(Modifier.weight(1f)) { GenerationBadge(g, small = true) }
            if (onToggleFav != null) {
                Icon(
                    if (g.isFavorite) Icons.Filled.Star else Icons.Filled.StarBorder,
                    contentDescription = if (g.isFavorite) "Hapus dari favorit" else "Jadikan favorit",
                    tint = if (g.isFavorite) Color(0xFFEAB308) else c.muted,
                    modifier = Modifier.size(22.dp).clip(CircleShape).clickable { onToggleFav(g) }.padding(2.dp),
                )
            }
        }
        Text(g.keyword, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink, modifier = Modifier.padding(top = 10.dp))
        val cap = g.captionText().lineSequence().firstOrNull().orEmpty()
        if (cap.isNotBlank()) Text(cap, fontSize = 13.sp, color = c.muted, maxLines = 3, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 4.dp), lineHeight = 18.sp)
        Row(Modifier.fillMaxWidth().padding(top = 14.dp), verticalAlignment = Alignment.CenterVertically) {
            RdBadge(m.label, m.color, small = true)
            Spacer(Modifier.width(8.dp))
            Text("${g.durasi} dtk", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = c.ink)
            if (g.videoUrl != null) { Spacer(Modifier.width(8.dp)); Icon(Icons.Filled.PlayCircle, null, tint = Fuchsia, modifier = Modifier.size(16.dp)) }
            Spacer(Modifier.weight(1f))
            Text(relativeTime(g.createdAt), fontSize = 12.sp, color = c.muted)
        }
    }
}

/** "5 menit lalu" — padanan relative() di web. (Tanpa java.time agar aman di API 24.) */
fun parseIso(iso: String?): java.util.Date? {
    if (iso.isNullOrBlank()) return null
    val clean = iso.replace(Regex("\\.\\d+"), "").replace("Z", "+00:00")
    val fixed = Regex("([+-]\\d{2}):(\\d{2})$").replace(clean) { it.groupValues[1] + it.groupValues[2] }
    for (pattern in listOf("yyyy-MM-dd'T'HH:mm:ssZ", "yyyy-MM-dd HH:mm:ss", "yyyy-MM-dd")) {
        try {
            val f = java.text.SimpleDateFormat(pattern, java.util.Locale.US)
            if (!pattern.endsWith("Z")) f.timeZone = java.util.TimeZone.getTimeZone("UTC")
            return f.parse(fixed)
        } catch (_: Exception) { }
    }
    return null
}

fun relativeTime(iso: String?): String {
    val d = parseIso(iso) ?: return iso?.take(10) ?: ""
    val diff = (System.currentTimeMillis() - d.time) / 1000
    return when {
        diff < 60 -> "baru saja"
        diff < 3600 -> "${diff / 60} menit lalu"
        diff < 86400 -> "${diff / 3600} jam lalu"
        diff < 86400 * 7 -> "${diff / 86400} hari lalu"
        else -> java.text.SimpleDateFormat("d MMM yyyy", java.util.Locale("id")).format(d)
    }
}

fun formatNumber(n: Long): String = java.text.NumberFormat.getInstance(java.util.Locale("id")).format(n)
