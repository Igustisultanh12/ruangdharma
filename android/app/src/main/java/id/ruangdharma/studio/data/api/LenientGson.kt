package id.ruangdharma.studio.data.api

import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonParser
import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter

/*
 * Gson "toleran": data dari server/Muse kadang tidak konsisten tipenya
 * (mis. estimasi_detik = 3.1 padahal int, "60" sebagai string, 1/0 untuk boolean,
 * atau objek di field teks). Gson bawaan langsung melempar exception dan SATU nilai
 * aneh membuat seluruh daftar gagal dimuat. Adapter di bawah membaca nilai apa pun
 * semampunya; nilai yang benar-benar tidak terbaca menjadi null → default model dipakai.
 */
object LenientGson {
    val gson: Gson by lazy { builder().create() }

    fun builder(): GsonBuilder = GsonBuilder()
        .registerTypeAdapter(Int::class.javaPrimitiveType, IntAdapter)
        .registerTypeAdapter(Int::class.javaObjectType, IntAdapter)
        .registerTypeAdapter(Long::class.javaPrimitiveType, LongAdapter)
        .registerTypeAdapter(Long::class.javaObjectType, LongAdapter)
        .registerTypeAdapter(Double::class.javaPrimitiveType, DoubleAdapter)
        .registerTypeAdapter(Double::class.javaObjectType, DoubleAdapter)
        .registerTypeAdapter(Boolean::class.javaPrimitiveType, BoolAdapter)
        .registerTypeAdapter(Boolean::class.javaObjectType, BoolAdapter)
        .registerTypeAdapter(String::class.java, StringAdapter)

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
}
