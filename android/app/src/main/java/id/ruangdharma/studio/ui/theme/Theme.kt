package id.ruangdharma.studio.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.R

/*
 * Token desain disamakan PERSIS dengan web (resources/css/app.css):
 * palet PT Kreatif Sky (violet → fuchsia → oranye) + aksen kuning "brutal"
 * (border tinta 2dp + bayangan offset), font Plus Jakarta Sans & Space Grotesk.
 */

val Violet = Color(0xFF7C3AED)
val Fuchsia = Color(0xFFC026D3)
val Orange = Color(0xFFF97316)
val Yellow = Color(0xFFFDE047)
val Lime = Color(0xFFBEF264)
val Zinc950 = Color(0xFF09090B)

val BrandColors = listOf(Violet, Fuchsia, Orange)
val BrandGradient: Brush get() = Brush.linearGradient(BrandColors)

@Immutable
data class RdColors(
    val dark: Boolean,
    val surface: Color,
    val surface2: Color,
    val card: Color,
    val ink: Color,
    val muted: Color,
    val line: Color,
    val shadow: Color,
)

private val LightRd = RdColors(
    dark = false,
    surface = Color(0xFFFBF8FF), surface2 = Color(0xFFF3EEFF), card = Color.White,
    ink = Color(0xFF15101F), muted = Color(0xFF6B6480), line = Color(0xFFE9E3F5),
    shadow = Color(0xFF15101F),
)
private val DarkRd = RdColors(
    dark = true,
    surface = Color(0xFF0C0916), surface2 = Color(0xFF161127), card = Color(0xFF19132D),
    ink = Color(0xFFF5F1FF), muted = Color(0xFFA8A0C2), line = Color(0xFF2D2547),
    shadow = Color(0xFF000000),
)

val LocalRd = staticCompositionLocalOf { LightRd }

object Rd {
    val colors: RdColors @Composable get() = LocalRd.current
}

val Jakarta = FontFamily(
    Font(R.font.jakarta_400, FontWeight.Normal),
    Font(R.font.jakarta_500, FontWeight.Medium),
    Font(R.font.jakarta_600, FontWeight.SemiBold),
    Font(R.font.jakarta_700, FontWeight.Bold),
    Font(R.font.jakarta_800, FontWeight.ExtraBold),
)
val SpaceGrotesk = FontFamily(
    Font(R.font.space_grotesk_500, FontWeight.Medium),
    Font(R.font.space_grotesk_600, FontWeight.SemiBold),
    Font(R.font.space_grotesk_700, FontWeight.Bold),
)

private val RdTypography = Typography().let { t ->
    Typography(
        displayLarge = t.displayLarge.copy(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold),
        displayMedium = t.displayMedium.copy(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold),
        displaySmall = t.displaySmall.copy(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold),
        headlineLarge = TextStyle(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 32.sp, lineHeight = 36.sp),
        headlineMedium = TextStyle(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 28.sp, lineHeight = 32.sp),
        headlineSmall = TextStyle(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 24.sp, lineHeight = 28.sp),
        titleLarge = TextStyle(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 20.sp, lineHeight = 26.sp),
        titleMedium = TextStyle(fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, lineHeight = 24.sp),
        titleSmall = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Bold, fontSize = 14.sp, lineHeight = 20.sp),
        bodyLarge = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Normal, fontSize = 16.sp, lineHeight = 24.sp),
        bodyMedium = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Normal, fontSize = 14.sp, lineHeight = 20.sp),
        bodySmall = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Normal, fontSize = 12.sp, lineHeight = 16.sp),
        labelLarge = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Bold, fontSize = 14.sp, lineHeight = 20.sp),
        labelMedium = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Bold, fontSize = 12.sp, lineHeight = 16.sp),
        labelSmall = TextStyle(fontFamily = Jakarta, fontWeight = FontWeight.Bold, fontSize = 11.sp, lineHeight = 14.sp),
    )
}

@Composable
fun RuangDharmaTheme(darkTheme: Boolean = isSystemInDarkTheme(), content: @Composable () -> Unit) {
    val rd = if (darkTheme) DarkRd else LightRd
    val scheme = if (darkTheme) {
        darkColorScheme(
            primary = Color(0xFFA78BFA), onPrimary = Color.White, secondary = Fuchsia, tertiary = Orange,
            background = rd.surface, onBackground = rd.ink, surface = rd.card, onSurface = rd.ink,
            surfaceVariant = rd.surface2, onSurfaceVariant = rd.muted, outline = rd.line, outlineVariant = rd.line,
            error = Color(0xFFFB7185),
        )
    } else {
        lightColorScheme(
            primary = Violet, onPrimary = Color.White, secondary = Fuchsia, tertiary = Orange,
            background = rd.surface, onBackground = rd.ink, surface = rd.card, onSurface = rd.ink,
            surfaceVariant = rd.surface2, onSurfaceVariant = rd.muted, outline = rd.line, outlineVariant = rd.line,
            error = Color(0xFFE11D48),
        )
    }
    CompositionLocalProvider(LocalRd provides rd) {
        MaterialTheme(colorScheme = scheme, typography = RdTypography, content = content)
    }
}
