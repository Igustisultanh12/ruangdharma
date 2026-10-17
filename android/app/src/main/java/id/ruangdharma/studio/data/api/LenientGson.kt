package id.ruangdharma.studio.data.api

import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonElement
import com.google.gson.JsonObject
import com.google.gson.JsonParser
import com.google.gson.TypeAdapter
import com.google.gson.reflect.TypeToken
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter
import id.ruangdharma.studio.data.model.NaskahPart
import java.io.StringReader
import java.lang.reflect.Type

/*
 * Gson "toleran": data dari server/Muse kadang tidak konsisten tipenya
 * (mis. estimasi_detik = 3.1 padahal int, "60" sebagai string, 1/0 untuk boolean,
 * atau naskah berupa string/objek asosiatif PHP {"0": ...}). Gson bawaan langsung
 * melempar exception dan SATU nilai aneh membuat seluruh daftar gagal dimuat.
 * Adapter di bawah membaca nilai apa pun semampunya; nilai yang benar-benar tidak
 * terbaca menjadi null/default.
 */
object LenientGson {
    val gson: Gson by lazy { builder().create() }

    fun builder(): GsonBuilder {
        val naskahListType: Type = object : TypeToken<List<NaskahPart>>() {}.type
        return GsonBuilder()
            .registerTypeAdapter(Int::class.javaPrimitiveType, IntAdapter)
            .registerTypeAdapter(Int::class.javaObjectType, IntAdapter)
            .registerTypeAdapter(Long::class.javaPrimitiveType, LongAdapter)
            .registerTypeAdapter(Long::class.javaObjectType, LongAdapter)
            .registerTypeAdapter(Double::class.javaPrimitiveType, DoubleAdapter)
            .registerTypeAdapter(Double::class.javaObjectType, DoubleAdapter)
            .registerTypeAdapter(Float::class.javaPrimitiveType, FloatAdapter)
            .registerTypeAdapter(Float::class.javaObjectType, FloatAdapter)
            .registerTypeAdapter(Boolean::class.javaPrimitiveType, BoolAdapter)
            .registerTypeAdapter(Boolean::class.javaObjectType, BoolAdapter)
            .registerTypeAdapter(String::class.java, StringAdapter)
            .registerTypeAdapter(NaskahPart::class.java, NaskahPartAdapter)
            .registerTypeAdapter(naskahListType, NaskahListAdapter)
    }

    /** Baca token berikutnya sebagai angka Double, atau null bila tidak bisa. */
    private fun readNumber(r: JsonReader): Double? = when (r.peek()) {
        JsonToken.NULL -> { r.nextNull(); null }
        JsonToken.NUMBER -> r.nextString().toDoubleOrNull()
        JsonToken.STRING -> r.nextString().trim().replace(",", ".").toDoubleOrNull()
        JsonToken.BOOLEAN -> if (r.nextBoolean()) 1.0 else 0.0
        else -> { r.skipValue(); null }
    }

    private object IntAdapter : TypeAdapter<Int?>() {
        override fun write(out: JsonWriter, value: Int?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): Int? = readNumber(r)?.let { if (it.isNaN()) null else Math.round(it).toInt() }
    }

    private object LongAdapter : TypeAdapter<Long?>() {
        override fun write(out: JsonWriter, value: Long?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): Long? = readNumber(r)?.let { if (it.isNaN()) null else Math.round(it) }
    }

    private object DoubleAdapter : TypeAdapter<Double?>() {
        override fun write(out: JsonWriter, value: Double?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): Double? = readNumber(r)
    }

    private object FloatAdapter : TypeAdapter<Float?>() {
        override fun write(out: JsonWriter, value: Float?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): Float? = readNumber(r)?.toFloat()
    }

    private object BoolAdapter : TypeAdapter<Boolean?>() {
        override fun write(out: JsonWriter, value: Boolean?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): Boolean? = when (r.peek()) {
            JsonToken.NULL -> { r.nextNull(); null }
            JsonToken.BOOLEAN -> r.nextBoolean()
            JsonToken.NUMBER -> (r.nextString().toDoubleOrNull() ?: 0.0) != 0.0
            JsonToken.STRING -> r.nextString().trim().lowercase() in setOf("1", "true", "yes", "on")
            else -> { r.skipValue(); null }
        }
    }

    private object StringAdapter : TypeAdapter<String?>() {
        override fun write(out: JsonWriter, value: String?) { if (value == null) out.nullValue() else out.value(value) }
        override fun read(r: JsonReader): String? = when (r.peek()) {
            JsonToken.NULL -> { r.nextNull(); null }
            JsonToken.STRING, JsonToken.NUMBER -> r.nextString()
            JsonToken.BOOLEAN -> r.nextBoolean().toString()
            // Objek/array di field teks: ambil "text"/"caption" bila ada, selain itu JSON mentahnya.
            else -> {
                val el = JsonParser.parseReader(r)
                if (el.isJsonObject) {
                    val o = el.asJsonObject
                    listOf("text", "caption", "message").firstNotNullOfOrNull { k ->
                        o.get(k)?.takeIf { it.isJsonPrimitive }?.asString
                    } ?: o.toString()
                } else el.toString()
            }
        }
    }

    private object NaskahPartAdapter : TypeAdapter<NaskahPart?>() {
        override fun write(out: JsonWriter, value: NaskahPart?) {
            if (value == null) { out.nullValue(); return }
            out.beginObject()
            out.name("bagian").value(value.bagian)
            value.judul?.let { out.name("judul").value(it) }
            value.teks?.let { out.name("teks").value(it) }
            value.dialog?.let { out.name("dialog").value(it) }
            value.narasi?.let { out.name("narasi").value(it) }
            value.speaker?.let { out.name("speaker").value(it) }
            value.suara?.let { out.name("suara").value(it) }
            value.promptVisual?.let { out.name("prompt_visual").value(it) }
            value.estimasiDetik?.let { out.name("estimasi_detik").value(it) }
            out.endObject()
        }

        override fun read(r: JsonReader): NaskahPart? {
            val tok = r.peek()
            if (tok == JsonToken.NULL) { r.nextNull(); return null }
            if (tok == JsonToken.STRING) {
                val s = r.nextString().trim()
                return if (s.isBlank()) null else NaskahPart(teks = s, narasi = s, dialog = s)
            }
            if (tok != JsonToken.BEGIN_OBJECT) {
                r.skipValue()
                return null
            }
            val el = JsonParser.parseReader(r)
            if (!el.isJsonObject) return null
            val o = el.asJsonObject

            val bagian = o.get("bagian")?.takeIf { !it.isJsonNull }?.let {
                try { it.asInt } catch (_: Exception) { Math.round(it.asFloat) }
            } ?: 0

            var text: String? = null
            for (k in listOf("teks", "dialog", "narasi")) {
                val item = o.get(k)
                if (item != null && !item.isJsonNull && item.isJsonPrimitive) {
                    text = item.asString
                    break
                }
            }

            fun optStr(k: String): String? = o.get(k)?.takeIf { !it.isJsonNull && it.isJsonPrimitive }?.asString

            val est = o.get("estimasi_detik")?.takeIf { !it.isJsonNull }?.let {
                try { Math.round(it.asFloat) } catch (_: Exception) { null }
            }

            return NaskahPart(
                bagian = bagian,
                judul = optStr("judul"),
                teks = text,
                dialog = text,
                narasi = text,
                speaker = optStr("speaker"),
                suara = optStr("suara"),
                promptVisual = optStr("prompt_visual"),
                estimasiDetik = est
            )
        }
    }

    private object NaskahListAdapter : TypeAdapter<List<NaskahPart>?>() {
        override fun write(out: JsonWriter, value: List<NaskahPart>?) {
            if (value == null) { out.nullValue(); return }
            out.beginArray()
            for (p in value) NaskahPartAdapter.write(out, p)
            out.endArray()
        }

        override fun read(r: JsonReader): List<NaskahPart>? {
            val tok = r.peek()
            if (tok == JsonToken.NULL) { r.nextNull(); return null }
            val list = mutableListOf<NaskahPart>()
            when (tok) {
                JsonToken.BEGIN_ARRAY -> {
                    r.beginArray()
                    var idx = 1
                    while (r.hasNext()) {
                        val p = NaskahPartAdapter.read(r)
                        if (p != null) {
                            val part = if (p.bagian == 0) p.copy(bagian = idx) else p
                            list.add(part)
                            idx++
                        }
                    }
                    r.endArray()
                }
                JsonToken.BEGIN_OBJECT -> {
                    r.beginObject()
                    var idx = 1
                    while (r.hasNext()) {
                        val name = r.nextName()
                        val p = NaskahPartAdapter.read(r)
                        if (p != null) {
                            val partNum = name.toIntOrNull() ?: idx
                            val part = if (p.bagian == 0) p.copy(bagian = partNum) else p
                            list.add(part)
                            idx++
                        }
                    }
                    r.endObject()
                }
                JsonToken.STRING -> {
                    val s = r.nextString().trim()
                    if (s.isNotBlank()) {
                        if (s.startsWith("[") || s.startsWith("{")) {
                            try {
                                val nested = read(JsonReader(StringReader(s)))
                                if (nested != null) return nested
                            } catch (_: Exception) {}
                        }
                        list.add(NaskahPart(bagian = 1, teks = s, narasi = s, dialog = s))
                    }
                }
                else -> r.skipValue()
            }
            return list
        }
    }
}
