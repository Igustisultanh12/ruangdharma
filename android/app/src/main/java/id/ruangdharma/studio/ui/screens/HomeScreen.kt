package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import id.ruangdharma.studio.data.model.Generation
import id.ruangdharma.studio.ui.components.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(generations: List<Generation>, loading: Boolean, refreshing: Boolean, error: String?, onRefresh: () -> Unit, onOpen: (Generation) -> Unit) {
    PullToRefreshBox(isRefreshing = refreshing, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
        when {
            loading && generations.isEmpty() -> LazyColumn { items(3) { ShimmerCard() } }
            error != null && generations.isEmpty() -> ErrorBox(error, onRefresh)
            generations.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = androidx.compose.ui.Alignment.Center) { Text("Belum ada video. Buat video pertamamu!") }
            else -> LazyColumn(contentPadding = PaddingValues(vertical = 8.dp)) {
                items(generations) { g -> FeedCard(g, onOpen) }
            }
        }
    }
}

@Composable
fun FeedCard(g: Generation, onOpen: (Generation) -> Unit) {
    Card(onClick = { onOpen(g) }, modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp)) {
        Column {
            val img = g.fullVideoUrl()
            if (img != null) {
                Box(Modifier.fillMaxWidth().height(220.dp)) {
                    AsyncImage(model = img, contentDescription = g.keyword, modifier = Modifier.fillMaxSize(), contentScale = ContentScale.Crop)
                }
            }
            Column(Modifier.padding(16.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(g.keyword, style = MaterialTheme.typography.titleMedium)
                    StatusBadge(g.status)
                }
                Spacer(Modifier.height(4.dp))
                Text(g.captionText().take(180), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                Text("Model: " + g.model + " • " + g.durasi + " dtk", style = MaterialTheme.typography.labelSmall)
            }
        }
    }
}
