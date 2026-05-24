<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Menyiapkan reaktivitas data
const guildInfo = ref({ name: 'Memanggil data...', level: 0, exp: 0, gold: 0 });
const questsList = ref([]);

// Fungsi memanggil API Laravel
const fetchBoardData = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/board');
    guildInfo.value = response.data.guild;
    questsList.value = response.data.quests;
  } catch (error) {
    console.error("Gagal terhubung ke Guild Master (Backend):", error);
  }
};

// Jalankan fungsi saat web pertama kali dimuat
onMounted(() => {
  fetchBoardData();
});
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-gray-200 p-8 font-sans">
    
    <header class="bg-gray-800 border-2 border-yellow-600 rounded-lg p-6 mb-10 shadow-2xl">
      <div class="flex justify-between items-center">
        
        <div>
          <h1 class="text-3xl font-bold text-yellow-500 tracking-widest uppercase mb-1">
            {{ guildInfo.name }}
          </h1>
          <p class="text-gray-400 font-semibold tracking-wider">
            LV. {{ guildInfo.level }} <span class="mx-2 text-gray-600">|</span> EXP: {{ guildInfo.exp }}
          </p>
        </div>

        <div class="bg-gray-900 px-6 py-3 rounded border border-gray-700 text-right">
          <p class="text-sm text-gray-500 uppercase tracking-widest mb-1">Guild Funds</p>
          <p class="text-2xl font-bold text-white">
            <span class="text-yellow-500 mr-2">💰</span>
            {{ guildInfo.gold }} <span class="text-sm text-yellow-600 font-normal">G</span>
          </p>
        </div>

      </div>
    </header>

    <main>
      <h2 class="text-xl text-gray-400 border-b border-gray-700 pb-2 mb-4">Papan Quest:</h2>
      <p class="text-gray-500 italic">Daftar kartu quest akan diletakkan di sini...</p>
    </main>

  </div>
</template>