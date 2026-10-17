package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import id.ruangdharma.studio.data.model.User

@Composable
fun ProfileScreen(user: User?, onLogout: () -> Unit) {
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Profil", style = MaterialTheme.typography.headlineSmall)
        Spacer(Modifier.height(12.dp))
        if (user != null) {
            Text(user.name, style = MaterialTheme.typography.titleLarge)
            Text(user.email, style = MaterialTheme.typography.bodyMedium)
            Text("Role: " + user.role, style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(12.dp))
            user.quota?.let { q -> Text("Kuota hari ini: " + q.remaining + " / " + q.limit + " (terpakai " + q.usedToday + ")") }
        } else Text("Belum login")
        Spacer(Modifier.height(24.dp))
        Button(onClick = onLogout, colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error)) { Text("Keluar") }
    }
}
