package id.ruangdharma.studio.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val Violet = Color(0xFF7C3AED)
private val Fuchsia = Color(0xFFD946EF)
private val Orange = Color(0xFFF97316)
private val Yellow = Color(0xFFFDE047)

private val LightColors = lightColorScheme(
    primary = Violet, secondary = Fuchsia, tertiary = Orange,
    surface = Color(0xFFFFFFFF), background = Color(0xFFF8F7FF), onPrimary = Color.White
)
private val DarkColors = darkColorScheme(
    primary = Color(0xFFA78BFA), secondary = Color(0xFFE879F9), tertiary = Color(0xFFFB923C),
    surface = Color(0xFF1C1917), background = Color(0xFF121212), onPrimary = Color.Black
)

@Composable
fun RuangDharmaTheme(darkTheme: Boolean = isSystemInDarkTheme(), content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = if (darkTheme) DarkColors else LightColors, content = content)
}
