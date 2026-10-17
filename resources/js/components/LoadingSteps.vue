<template><div><p v-for="(s,i) in steps" :key="s" :class="i===current ? 'font-bold text-violet-700' : 'text-slate-500'">{{ i < current ? '✅' : i===current ? '⏳' : '○' }} {{ s }}</p></div></template>
<script setup>defineProps({steps:{type:Array,default:()=>[]}, current:{type:Number,default:0}});</script>
