package id.ruangdharma.studio.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*
import java.util.Calendar

/** Padanan layouts/GuestLayout.vue + views/Login.vue. */
@Composable
fun LoginScreen(
    onLogin: (String, String) -> Unit,
    loading: Boolean,
    error: String?,
    dark: Boolean = false,
    onToggleDark: () -> Unit = {},
) {
    val c = Rd.colors
    var email by rememberSaveable { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    val canSubmit = !loading && email.isNotBlank() && password.isNotBlank()
    val submit = { if (canSubmit) onLogin(email.trim(), password) }

    Column(
        Modifier.fillMaxSize().background(c.surface).statusBarsPadding().navigationBarsPadding().imePadding()
            .verticalScroll(rememberScrollState()).padding(horizontal = 20.dp, vertical = 16.dp),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            RdLogo(Modifier.weight(1f))
            Box(
                Modifier.size(42.dp).clip(RoundedCornerShape(14.dp)).clickable(onClick = onToggleDark),
                contentAlignment = Alignment.Center,
            ) { Icon(if (dark) Icons.Filled.LightMode else Icons.Filled.DarkMode, if (dark) "Mode terang" else "Mode gelap", tint = c.muted) }
        }

        Spacer(Modifier.height(20.dp))

        // Panel gradasi (aside GuestLayout) dalam versi ringkas untuk layar ponsel.
        BrutalBox(Modifier.fillMaxWidth().padding(end = 5.dp, bottom = 5.dp), shape = RoundedCornerShape(28.dp), brush = BrandGradient) {
            Box(Modifier.matchParentSize().dotBackground())
            Box(Modifier.align(Alignment.TopEnd).padding(top = 10.dp, end = 10.dp).rotate(12f)) { CanangMark(size = 78, frame = Color.White) }
            Column(Modifier.padding(20.dp)) {
                Text(
                    "Cerita dharma untuk anak, siap tayang hari ini.",
                    fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 24.sp, lineHeight = 27.sp,
                    color = Color.White, modifier = Modifier.padding(end = 70.dp),
                )
                Spacer(Modifier.height(14.dp))
                listOf(
                    Icons.Filled.AutoFixHigh to "Naskah 6 bagian, prompt visual 9:16 & caption sekali klik",
                    Icons.Filled.FactCheck to "Alur review: draf, revisi, disetujui, terbit",
                    Icons.Filled.Sensors to "Semua menu terbarui otomatis tanpa refresh",
                ).forEach { (ic, t) ->
                    val shape = RoundedCornerShape(16.dp)
                    Row(
                        Modifier.fillMaxWidth().padding(vertical = 4.dp).clip(shape).background(Color.White.copy(alpha = 0.1f))
                            .border(1.dp, Color.White.copy(alpha = 0.2f), shape).padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(ic, null, tint = Color(0xFFFDE047), modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(10.dp))
                        Text(t, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, lineHeight = 16.sp)
                    }
                }
            }
        }

        Spacer(Modifier.height(28.dp))
        Eyebrow("Studio internal")
        Spacer(Modifier.height(16.dp))
        Text("Om Swastyastu 🙏", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 34.sp, color = c.ink)
        Text("Masuk untuk lanjut membuat video cerita dharma.", fontSize = 15.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))

        AnimatedVisibility(visible = !error.isNullOrBlank()) {
            AlertBox(error.orEmpty(), Modifier.padding(top = 20.dp))
        }

        Spacer(Modifier.height(24.dp))
        FieldLabel("Email")
        RdTextField(
            email, { email = it }, placeholder = "nama@ruangdharma.id", leading = Icons.Filled.MailOutline,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
        )
        Spacer(Modifier.height(18.dp))
        FieldLabel("Password")
        RdTextField(
            password, { password = it }, leading = Icons.Filled.Lock, password = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Go),
        )
        Spacer(Modifier.height(24.dp))
        GradientButton(
            text = if (loading) "Memeriksa…" else "Masuk",
            onClick = submit,
            icon = Icons.Filled.Login,
            enabled = canSubmit,
            loading = loading,
            large = true,
            modifier = Modifier.fillMaxWidth(),
        )

        Spacer(Modifier.height(32.dp))
        Text(
            "© ${Calendar.getInstance().get(Calendar.YEAR)} Ruang Dharma · @ruangdharma_",
            fontSize = 12.sp, color = c.muted, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
        )
    }
}
