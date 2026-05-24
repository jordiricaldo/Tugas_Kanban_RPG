<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const API = 'http://127.0.0.1:8000/api';

// ─── Auth State ───────────────────────────────────────────────────────────────
const authToken  = ref(localStorage.getItem('auth_token') || null);
const authUser   = ref(null);
const myPlayer   = ref(null);
const authScreen = ref('login'); // 'login' | 'register'
const authLoading = ref(false);
const authError  = ref('');

// Register flow
const registerStep  = ref(1); // 1=creds, 2=class, 3=guild
const registerAction = ref('skip_guild'); // 'create_guild' | 'join_guild' | 'skip_guild'
const registerForm  = ref({
  name: '', email: '', password: '', password_confirmation: '',
  class: 'Warrior', guild_name: '', invite_code: ''
});

// Login form
const loginForm = ref({ email: '', password: '' });

// Set axios default header
if (authToken.value) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${authToken.value}`;
}

// ─── App State ────────────────────────────────────────────────────────────────
const guildInfo  = ref({ name: 'Memuat...', level: 0, exp: 0, gold: 0 });
const questsList = ref([]);
const playersList = ref([]);
const loading    = ref(true);
const toast      = ref(null);
const activeView = ref('board'); // 'board' | 'leaderboard' | 'players' | 'guild_lb'

// Modal quest
const showQuestModal = ref(false);
const isEditing      = ref(false);
const editingQuestId = ref(null);
const questForm = ref({ title:'', description:'', rank:'C', reward_exp:50, reward_gold:100, doomsday:'', player_id:'' });

// Modal buat guild (dari no-guild screen)
const showCreateGuildModal = ref(false);
const createGuildForm = ref({ guild_name: '' });
const createGuildLoading = ref(false);

// Guild leaderboard
const guildLeaderboard = ref([]);

// Guild search & join (untuk user tanpa guild)
const guildSearchQuery    = ref('');
const guildSearchResults  = ref([]);
const guildSearchLoading  = ref(false);
const myJoinRequests      = ref([]);
const joinRequests        = ref([]); // untuk GM: pending requests ke guild dia
const joinRequestsLoading = ref(false);

// Drag
const draggingId = ref(null);

// ─── Columns ──────────────────────────────────────────────────────────────────
const columns = [
  { key:'available', label:'Available', icon:'📜', color:'#7c6af7', glow:'rgba(124,106,247,0.4)' },
  { key:'accepted',  label:'Accepted',  icon:'⚔️',  color:'#f0b429', glow:'rgba(240,180,41,0.4)'  },
  { key:'in_battle', label:'In Battle', icon:'🔥', color:'#ef4444', glow:'rgba(239,68,68,0.4)'   },
  { key:'cleared',   label:'Cleared',   icon:'💎', color:'#22c55e', glow:'rgba(34,197,94,0.4)'   },
];

const rankConfig = {
  S: { color:'#ffd700', glow:'0 0 8px #ffd700aa', label:'S' },
  A: { color:'#ff6b6b', glow:'0 0 8px #ff6b6baa', label:'A' },
  B: { color:'#4fc3f7', glow:'0 0 8px #4fc3f7aa', label:'B' },
  C: { color:'#9e9e9e', glow:'0 0 4px #9e9e9eaa', label:'C' },
};

const classConfig = {
  Warrior: { icon:'🗡️',  color:'#ef4444' },
  Mage:    { icon:'🔮', color:'#a78bfa' },
  Rogue:   { icon:'🗡️',  color:'#34d399' },
  Archer:  { icon:'🏹', color:'#fbbf24' },
};

// ─── Computed ─────────────────────────────────────────────────────────────────
const isLoggedIn = computed(() => !!authToken.value && !!authUser.value);
const isGM       = computed(() => myPlayer.value?.role === 'guild_master');
const hasGuild   = computed(() => !!myPlayer.value?.guild_id);

const questsByStatus = (status) => questsList.value.filter(q => q.status === status);

const expPercent = computed(() => {
  const needed = (guildInfo.value.level || 1) * 1000;
  return Math.min(100, Math.round(((guildInfo.value.exp || 0) / needed) * 100));
});

const sortedLeaderboard = computed(() =>
  [...playersList.value].sort((a,b) =>
    b.quests_cleared - a.quests_cleared || b.level - a.level
  )
);

// ─── Auth Functions ────────────────────────────────────────────────────────────
const doLogin = async () => {
  authError.value = '';
  authLoading.value = true;
  try {
    const res = await axios.post(`${API}/auth/login`, loginForm.value);
    await setAuth(res.data);
    if (res.data.player?.guild_id) await fetchBoard();
  } catch (e) {
    authError.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Login gagal.';
  } finally {
    authLoading.value = false;
  }
};

const doRegister = async () => {
  authError.value = '';
  authLoading.value = true;
  try {
    const payload = { ...registerForm.value, action: registerAction.value };
    const res = await axios.post(`${API}/auth/register`, payload);
    await setAuth(res.data);
    if (res.data.player?.guild_id) await fetchBoard();
  } catch (e) {
    authError.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Registrasi gagal.';
    authLoading.value = false;
  }
};

const doLogout = async () => {
  try { await axios.post(`${API}/auth/logout`); } catch {}
  clearAuth();
};

const setAuth = async (data) => {
  authToken.value  = data.token;
  authUser.value   = data.user;
  myPlayer.value   = data.player;
  if (data.guild) guildInfo.value = data.guild;
  localStorage.setItem('auth_token', data.token);
  axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
  // Load join requests jika user belum punya guild
  if (!data.player?.guild_id) {
    await fetchMyJoinRequests();
    await searchGuilds();
  } else if (data.player?.role === 'guild_master') {
    await fetchJoinRequests();
  }
};

const clearAuth = () => {
  authToken.value = null;
  authUser.value  = null;
  myPlayer.value  = null;
  localStorage.removeItem('auth_token');
  delete axios.defaults.headers.common['Authorization'];
  guildInfo.value  = { name: 'Memuat...', level: 0, exp: 0, gold: 0 };
  questsList.value  = [];
  playersList.value = [];
  activeView.value  = 'board';
  // Reset ke halaman login, bukan register
  authScreen.value   = 'login';
  registerStep.value = 1;
  registerAction.value = 'skip_guild';
  authError.value = '';
};

const tryRestoreSession = async () => {
  if (!authToken.value) { loading.value = false; return; }
  try {
    const res = await axios.get(`${API}/auth/me`);
    authUser.value  = res.data.user;
    myPlayer.value  = res.data.player;
    if (res.data.guild) guildInfo.value = res.data.guild;
    if (res.data.player?.guild_id) {
      await fetchBoard();
      if (res.data.player?.role === 'guild_master') await fetchJoinRequests();
    } else {
      // User tanpa guild: load search + my requests
      await fetchMyJoinRequests();
      await searchGuilds();
      loading.value = false;
    }
  } catch {
    clearAuth();
    loading.value = false;
  }
};

// ─── API ──────────────────────────────────────────────────────────────────────
const fetchBoard = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`${API}/board`);
    guildInfo.value   = res.data.guild;
    questsList.value  = res.data.quests;
    playersList.value = res.data.players || [];
    if (res.data.my_player) myPlayer.value = res.data.my_player;
  } catch { showToast('❌ Koneksi ke Guild Master gagal!', 'error'); }
  finally  { loading.value = false; }
};

const fetchGuildLeaderboard = async () => {
  try {
    const res = await axios.get(`${API}/guilds/leaderboard`);
    guildLeaderboard.value = res.data;
  } catch {}
};

// ── Guild Search & Join ─────────────────────────────────────────────────────
const searchGuilds = async () => {
  guildSearchLoading.value = true;
  try {
    const res = await axios.get(`${API}/guilds/search`, { params: { q: guildSearchQuery.value } });
    guildSearchResults.value = res.data;
  } catch {
    showToast('❌ Gagal mencari guild.', 'error');
  } finally {
    guildSearchLoading.value = false;
  }
};

const requestJoinGuild = async (guildId) => {
  try {
    const res = await axios.post(`${API}/guilds/${guildId}/request-join`);
    showToast(res.data.message || '📨 Request join terkirim!');
    await fetchMyJoinRequests();
    // Refresh search results to update status
    await searchGuilds();
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal request join.', 'error');
  }
};

const fetchMyJoinRequests = async () => {
  try {
    const res = await axios.get(`${API}/guilds/my-requests`);
    myJoinRequests.value = res.data;
  } catch {}
};

// ── GM: Join Requests Management ───────────────────────────────────────────
const fetchJoinRequests = async () => {
  joinRequestsLoading.value = true;
  try {
    const res = await axios.get(`${API}/guilds/join-requests`);
    joinRequests.value = res.data;
  } catch {}
  finally { joinRequestsLoading.value = false; }
};

const acceptJoinRequest = async (id, playerName) => {
  try {
    const res = await axios.post(`${API}/guilds/join-requests/${id}/accept`);
    showToast(res.data.message || `✅ ${playerName} bergabung!`);
    await fetchJoinRequests();
    await fetchBoard();
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal accept.', 'error');
  }
};

const rejectJoinRequest = async (id, playerName) => {
  try {
    await axios.post(`${API}/guilds/join-requests/${id}/reject`);
    showToast(`❌ Request dari ${playerName} ditolak.`);
    await fetchJoinRequests();
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal reject.', 'error');
  }
};

// helper: cek apakah sudah ada pending request ke guild tertentu
const hasPendingRequest = (guildId) =>
  myJoinRequests.value.some(r => r.guild?.id === guildId && r.status === 'pending');

// Buat guild dari dalam app (user sudah login tapi belum punya guild)
const createGuildFromApp = async () => {
  if (!createGuildForm.value.guild_name.trim()) {
    showToast('❌ Nama guild tidak boleh kosong.', 'error'); return;
  }
  createGuildLoading.value = true;
  try {
    const res = await axios.post(`${API}/guilds/create`, { guild_name: createGuildForm.value.guild_name });
    myPlayer.value = res.data.player;
    guildInfo.value = res.data.guild;
    showCreateGuildModal.value = false;
    showToast(`⚜ Guild "${res.data.guild.name}" berhasil dibuat!`);
    await fetchBoard();
    await fetchJoinRequests();
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal membuat guild.', 'error');
  } finally {
    createGuildLoading.value = false;
  }
};

const refreshGuild = async () => {
  const res = await axios.get(`${API}/guild`);
  guildInfo.value = res.data;
};

const refreshPlayers = async () => {
  const res = await axios.get(`${API}/players`);
  playersList.value = res.data;
};

// Quest CRUD
const submitQuestForm = async () => {
  const payload = { ...questForm.value, player_id: questForm.value.player_id || null };
  try {
    if (isEditing.value) {
      const res = await axios.patch(`${API}/quests/${editingQuestId.value}`, payload);
      const idx = questsList.value.findIndex(q => q.id === editingQuestId.value);
      if (idx !== -1) questsList.value[idx] = res.data;
      showToast('✏️ Quest diperbarui!');
    } else {
      const res = await axios.post(`${API}/quests`, payload);
      questsList.value.push(res.data);
      showToast('📜 Quest baru diposting!');
    }
    closeQuestModal();
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal menyimpan quest.', 'error');
  }
};

const deleteQuest = async (id) => {
  if (!isGM.value) { showToast('❌ Hanya Guild Master yang bisa hapus quest.', 'error'); return; }
  if (!confirm('Hapus quest ini?')) return;
  try {
    await axios.delete(`${API}/quests/${id}`);
    questsList.value = questsList.value.filter(q => q.id !== id);
    showToast('🗑️ Quest dihapus.');
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal hapus quest.', 'error');
  }
};

const moveQuest = async (questId, newStatus) => {
  const idx = questsList.value.findIndex(q => q.id === questId);
  if (idx === -1) return;
  const oldStatus = questsList.value[idx].status;
  if (oldStatus === newStatus) return;

  const { reward_exp, reward_gold } = questsList.value[idx];
  questsList.value[idx] = { ...questsList.value[idx], status: newStatus };

  try {
    const res = await axios.patch(`${API}/quests/${questId}`, { status: newStatus });
    questsList.value[idx] = res.data;
    if (newStatus === 'cleared') {
      await Promise.all([refreshGuild(), refreshPlayers()]);
      showToast(`💎 Quest Cleared! +${reward_exp} EXP, +${reward_gold} Gold`);
    } else {
      showToast(`🔄 Quest → ${newStatus.replace('_',' ')}`);
    }
  } catch {
    questsList.value[idx] = { ...questsList.value[idx], status: oldStatus };
    showToast('❌ Gagal memindah quest.', 'error');
  }
};

const deletePlayer = async (id) => {
  if (!isGM.value) { showToast('❌ Hanya Guild Master yang bisa kick member.', 'error'); return; }
  if (!confirm('Kick player dari guild?')) return;
  try {
    await axios.delete(`${API}/players/${id}`);
    playersList.value = playersList.value.filter(p => p.id !== id);
    showToast('👢 Player dikeluarkan dari guild.');
  } catch (e) {
    showToast(e.response?.data?.message || '❌ Gagal kick player.', 'error');
  }
};

// ─── Modal helpers ────────────────────────────────────────────────────────────
const openCreateQuest = () => {
  isEditing.value = false; editingQuestId.value = null;
  questForm.value = { title:'', description:'', rank:'C', reward_exp:50, reward_gold:100, doomsday:'', player_id:'' };
  showQuestModal.value = true;
};
const openEditQuest = (quest) => {
  isEditing.value = true; editingQuestId.value = quest.id;
  questForm.value = { title:quest.title, description:quest.description||'', rank:quest.rank,
    reward_exp:quest.reward_exp, reward_gold:quest.reward_gold,
    doomsday:quest.doomsday||'', player_id:quest.player_id||'' };
  showQuestModal.value = true;
};
const closeQuestModal = () => { showQuestModal.value = false; };

const switchToGuildLB = () => {
  fetchGuildLeaderboard();
  activeView.value = 'guild_lb';
};

const switchToPlayers = () => {
  activeView.value = 'players';
  if (isGM.value) fetchJoinRequests();
};

// ─── Drag & Drop ──────────────────────────────────────────────────────────────
const onDragStart = (e, quest) => { draggingId.value = quest.id; e.dataTransfer.effectAllowed = 'move'; };
const onDragOver  = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; };
const onDrop = (e, status) => {
  e.preventDefault();
  if (draggingId.value) moveQuest(draggingId.value, status);
  draggingId.value = null;
};

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer = null;
const showToast = (msg, type='success') => {
  toast.value = { msg, type };
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = null; }, 3500);
};

// ─── Pixel Art Avatars ────────────────────────────────────────────────────────
const pixelAvatar = (cls) => {
  const colors = { Warrior:'#ef4444', Mage:'#a78bfa', Rogue:'#34d399', Archer:'#fbbf24' };
  const c = colors[cls] || '#9e9e9e';
  const sprites = {
    Warrior: `<rect x="5" y="0" width="6" height="6" fill="${c}"/>
              <rect x="4" y="1" width="1" height="4" fill="${c}"/>
              <rect x="11" y="1" width="1" height="4" fill="${c}"/>
              <rect x="6" y="2" width="2" height="2" fill="#111"/>
              <rect x="3" y="6" width="10" height="7" fill="${c}"/>
              <rect x="1" y="6" width="3" height="6" fill="${c}"/>
              <rect x="12" y="6" width="3" height="6" fill="${c}"/>
              <rect x="3" y="13" width="4" height="3" fill="${c}"/>
              <rect x="9" y="13" width="4" height="3" fill="${c}"/>
              <rect x="5" y="7" width="6" height="1" fill="#111" opacity=".4"/>`,
    Mage:    `<rect x="5" y="0" width="6" height="6" fill="${c}"/>
              <rect x="3" y="1" width="2" height="3" fill="${c}"/>
              <rect x="11" y="1" width="2" height="3" fill="${c}"/>
              <rect x="6" y="2" width="2" height="2" fill="#fff" opacity=".8"/>
              <rect x="4" y="6" width="8" height="6" fill="${c}"/>
              <rect x="2" y="7" width="2" height="5" fill="${c}"/>
              <rect x="12" y="7" width="2" height="5" fill="${c}"/>
              <rect x="4" y="12" width="3" height="4" fill="${c}"/>
              <rect x="9" y="12" width="3" height="4" fill="${c}"/>
              <rect x="7" y="0" width="2" height="2" fill="#ffd700"/>`,
    Rogue:   `<rect x="5" y="1" width="6" height="5" fill="${c}"/>
              <rect x="4" y="2" width="1" height="3" fill="${c}"/>
              <rect x="11" y="2" width="1" height="3" fill="${c}"/>
              <rect x="6" y="2" width="2" height="2" fill="#111" opacity=".7"/>
              <rect x="4" y="6" width="8" height="6" fill="${c}"/>
              <rect x="2" y="6" width="3" height="7" fill="${c}"/>
              <rect x="11" y="6" width="3" height="7" fill="${c}"/>
              <rect x="4" y="12" width="3" height="4" fill="${c}"/>
              <rect x="9" y="12" width="3" height="4" fill="${c}"/>
              <rect x="4" y="0" width="8" height="3" fill="#333"/>`,
    Archer:  `<rect x="5" y="1" width="6" height="5" fill="${c}"/>
              <rect x="4" y="2" width="1" height="3" fill="${c}"/>
              <rect x="11" y="2" width="1" height="3" fill="${c}"/>
              <rect x="6" y="2" width="2" height="2" fill="#111" opacity=".6"/>
              <rect x="4" y="6" width="8" height="5" fill="${c}"/>
              <rect x="2" y="6" width="2" height="5" fill="${c}"/>
              <rect x="12" y="6" width="2" height="5" fill="${c}"/>
              <rect x="4" y="11" width="3" height="5" fill="${c}"/>
              <rect x="9" y="11" width="3" height="5" fill="${c}"/>
              <rect x="13" y="3" width="1" height="8" fill="#8B4513"/>
              <rect x="13" y="3" width="3" height="1" fill="${c}"/>
              <rect x="13" y="10" width="3" height="1" fill="${c}"/>`,
  };
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="48" height="48" style="image-rendering:pixelated">${sprites[cls]||sprites.Warrior}</svg>`;
};

onMounted(tryRestoreSession);
</script>

<template>
  <div class="dungeon-app">
    <div class="scanlines" aria-hidden="true"></div>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" :class="['toast-notif', toast.type]">
        <span class="toast-text">{{ toast.msg }}</span>
      </div>
    </Transition>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- AUTH SCREEN                                         -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div v-if="!isLoggedIn" class="auth-screen">
      <div class="auth-bg-grid"></div>
      <div class="auth-container">
        <div class="auth-logo">
          <div class="auth-logo-icon">⚜</div>
          <div class="auth-logo-text">DARK GUILD SYSTEM</div>
          <div class="auth-logo-sub">v2.0 — QUEST BOARD RPG</div>
        </div>

        <!-- LOGIN -->
        <div v-if="authScreen === 'login'" class="auth-panel">
          <div class="auth-panel-title">MASUK KE GUILD</div>
          <div class="auth-fields">
            <div class="auth-field">
              <label>EMAIL</label>
              <input v-model="loginForm.email" type="email" placeholder="adventurer@guild.com" @keyup.enter="doLogin" />
            </div>
            <div class="auth-field">
              <label>PASSWORD</label>
              <input v-model="loginForm.password" type="password" placeholder="••••••••" @keyup.enter="doLogin" />
            </div>
          </div>
          <div v-if="authError" class="auth-error">⚠ {{ authError }}</div>
          <button class="pixel-btn auth-btn" :disabled="authLoading" @click="doLogin">
            {{ authLoading ? 'LOADING...' : '⚔ MASUK' }}
          </button>
          <div class="auth-switch">
            Belum punya akun?
            <button @click="authScreen='register'; registerStep=1; authError=''" class="auth-link">DAFTAR SEKARANG</button>
          </div>
        </div>

        <!-- REGISTER — Step 1: Credentials -->
        <div v-else-if="authScreen === 'register' && registerStep === 1" class="auth-panel">
          <div class="auth-panel-title">DAFTAR ADVENTURER — 1/3</div>
          <div class="auth-step-dots">
            <span class="dot active"></span><span class="dot"></span><span class="dot"></span>
          </div>
          <div class="auth-fields">
            <div class="auth-field">
              <label>NAMA ADVENTURER</label>
              <input v-model="registerForm.name" placeholder="Masukkan namamu..." maxlength="50" />
            </div>
            <div class="auth-field">
              <label>EMAIL</label>
              <input v-model="registerForm.email" type="email" placeholder="email@guild.com" />
            </div>
            <div class="auth-field">
              <label>PASSWORD</label>
              <input v-model="registerForm.password" type="password" placeholder="Min. 6 karakter" />
            </div>
            <div class="auth-field">
              <label>KONFIRMASI PASSWORD</label>
              <input v-model="registerForm.password_confirmation" type="password" placeholder="Ulangi password" @keyup.enter="registerStep=2" />
            </div>
          </div>
          <div v-if="authError" class="auth-error">⚠ {{ authError }}</div>
          <button class="pixel-btn auth-btn" @click="registerStep=2; authError=''">LANJUT →</button>
          <div class="auth-switch">
            Sudah punya akun?
            <button @click="authScreen='login'; authError=''" class="auth-link">MASUK</button>
          </div>
        </div>

        <!-- REGISTER — Step 2: Pick Class -->
        <div v-else-if="authScreen === 'register' && registerStep === 2" class="auth-panel">
          <div class="auth-panel-title">PILIH CLASS — 2/3</div>
          <div class="auth-step-dots">
            <span class="dot active"></span><span class="dot active"></span><span class="dot"></span>
          </div>
          <div class="auth-class-preview">
            <div v-html="pixelAvatar(registerForm.class)" class="auth-big-avatar"></div>
            <div class="auth-class-name" :style="{ color: classConfig[registerForm.class]?.color }">
              {{ classConfig[registerForm.class]?.icon }} {{ registerForm.class }}
            </div>
          </div>
          <div class="auth-class-grid">
            <button
              v-for="cls in ['Warrior','Mage','Rogue','Archer']"
              :key="cls"
              :class="['auth-cls-btn', registerForm.class === cls && 'selected']"
              :style="registerForm.class===cls ? { borderColor: classConfig[cls].color, color: classConfig[cls].color } : {}"
              @click="registerForm.class = cls"
            >
              <div v-html="pixelAvatar(cls)" style="transform:scale(0.7);margin-bottom:.2rem"></div>
              <span>{{ cls.toUpperCase() }}</span>
            </button>
          </div>
          <div class="auth-nav-row">
            <button class="pixel-btn ghost" @click="registerStep=1">← BACK</button>
            <button class="pixel-btn auth-btn" @click="registerStep=3">LANJUT →</button>
          </div>
        </div>

        <!-- REGISTER — Step 3: Guild -->
        <div v-else-if="authScreen === 'register' && registerStep === 3" class="auth-panel">
          <div class="auth-panel-title">PILIH GUILD — 3/3</div>
          <div class="auth-step-dots">
            <span class="dot active"></span><span class="dot active"></span><span class="dot active"></span>
          </div>
          <div class="auth-guild-tabs">
            <button
              :class="['auth-tab', registerAction==='skip_guild' && 'active']"
              @click="registerAction='skip_guild'"
            >🚶 LEWATI</button>
            <button
              :class="['auth-tab', registerAction==='create_guild' && 'active']"
              @click="registerAction='create_guild'"
            >⛜ BUAT GUILD</button>
            <button
              :class="['auth-tab', registerAction==='join_guild' && 'active']"
              @click="registerAction='join_guild'"
            >🔗 JOIN GUILD</button>
          </div>
          <div class="auth-fields">
            <template v-if="registerAction === 'skip_guild'">
              <div class="auth-guild-info">
                <span>🚶 Kamu bisa bergabung guild nanti</span>
                <span>📜 Akun langsung dibuat tanpa guild</span>
                <span>💡 Minta invite code dari Guild Master untuk bergabung</span>
              </div>
            </template>
            <template v-else-if="registerAction === 'create_guild'">
              <div class="auth-field">
                <label>NAMA GUILD BARU</label>
                <input v-model="registerForm.guild_name" placeholder="Shadow Dragons, Iron Wolves..." maxlength="100" />
              </div>
              <div class="auth-guild-info">
                <span>⭐ Kamu akan jadi <strong>Guild Master</strong></span>
                <span>🔑 Invite code akan dibuat otomatis</span>
                <span>⚔ Semua member bisa post quest</span>
                <span>🗑 Hanya GM yang bisa hapus quest &amp; kick member</span>
              </div>
            </template>
            <template v-else>
              <div class="auth-field">
                <label>INVITE CODE GUILD</label>
                <input v-model="registerForm.invite_code" placeholder="Contoh: AB1C2D" maxlength="6" style="text-transform:uppercase" @input="registerForm.invite_code = registerForm.invite_code.toUpperCase()" />
              </div>
              <div class="auth-guild-info">
                <span>👥 Kamu akan jadi <strong>Member</strong> guild</span>
                <span>📜 Bisa lihat dan post quest</span>
                <span>🏆 Bersaing di leaderboard guild</span>
              </div>
            </template>
          </div>
          <div v-if="authError" class="auth-error">⚠ {{ authError }}</div>
          <div class="auth-nav-row">
            <button class="pixel-btn ghost" @click="registerStep=2">← BACK</button>
            <button class="pixel-btn auth-btn" :disabled="authLoading" @click="doRegister">
              {{ authLoading ? 'LOADING...' : '✅ SELESAI' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- MAIN APP (logged in)                                -->
    <!-- ═══════════════════════════════════════════════════ -->
    <template v-else>
      <!-- NAVBAR -->
      <nav class="dungeon-nav">
        <div class="nav-brand">
          <span class="brand-icon">⚜</span>
          <div class="brand-text">
            <span class="brand-name">{{ hasGuild ? guildInfo.name : 'DARK GUILD SYSTEM' }}</span>
            <span class="brand-sub">DARK GUILD SYSTEM v2.0</span>
          </div>
        </div>

        <div v-if="hasGuild" class="nav-guild-stats">
          <div class="guild-stat">
            <span class="gstat-label">LEVEL</span>
            <span class="gstat-val gold-glow">{{ guildInfo.level }}</span>
          </div>
          <div class="exp-track">
            <div class="exp-label-row">
              <span class="gstat-label">EXP</span>
              <span class="gstat-label">{{ guildInfo.exp }}</span>
            </div>
            <div class="exp-rail">
              <div class="exp-fill" :style="{ width: expPercent + '%' }"></div>
            </div>
          </div>
          <div class="guild-stat">
            <span class="gstat-label">GOLD</span>
            <span class="gstat-val gold-glow">{{ (guildInfo.gold||0).toLocaleString() }}</span>
          </div>
        </div>

        <div v-if="hasGuild" class="nav-tabs">
          <button :class="['nav-tab', activeView==='board' && 'active']"       @click="activeView='board'">📋 BOARD</button>
          <button :class="['nav-tab', activeView==='leaderboard' && 'active']" @click="activeView='leaderboard'">🏆 RANKING</button>
          <button :class="['nav-tab', activeView==='players' && 'active']"     @click="switchToPlayers">⚔️ PARTY<span v-if="isGM && joinRequests.length" class="notif-dot">{{ joinRequests.length }}</span></button>
          <button :class="['nav-tab', activeView==='guild_lb' && 'active']"    @click="switchToGuildLB">🌐 GUILDS</button>
        </div>

        <!-- My Profile + Logout -->
        <div class="nav-profile">
          <div v-if="myPlayer" class="nav-me" :style="{ borderColor: classConfig[myPlayer.class]?.color }">
            <div v-html="pixelAvatar(myPlayer.class)" style="transform:scale(0.6)"></div>
            <div class="nav-me-info">
              <span class="nav-me-name">{{ myPlayer.name }}</span>
              <span class="nav-me-role" :style="{ color: isGM ? '#ffd700' : '#5a5a8a' }">
                {{ isGM ? '👑 GM' : hasGuild ? '⚔ Member' : '🚶 No Guild' }}
              </span>
            </div>
          </div>
          <button class="pixel-btn ghost nav-logout" @click="doLogout" title="Logout">↩ LOGOUT</button>
        </div>

        <button v-if="hasGuild" class="btn-post-quest pixel-btn" @click="openCreateQuest">+ POST QUEST</button>
      </nav>

      <!-- ══ NO-GUILD SCREEN ══════════════════════════════════════ -->
      <div v-if="!hasGuild" class="no-guild-screen">
        <div class="no-guild-header">
          <div class="no-guild-icon">🏰</div>
          <h2 class="no-guild-title">BELUM ADA GUILD</h2>
          <p class="no-guild-sub">Cari guild untuk bergabung, atau buat guild baru</p>
        </div>

        <!-- Status request join yang sudah dikirim -->
        <div v-if="myJoinRequests.length" class="my-requests-box">
          <div class="my-req-title">📨 Request Join Kamu</div>
          <div v-for="req in myJoinRequests" :key="req.id" class="my-req-row">
            <span class="req-guild-name">{{ req.guild?.name }}</span>
            <span :class="['req-status', `req-${req.status}`]">
              {{ req.status === 'pending' ? '⏳ Menunggu' : req.status === 'accepted' ? '✅ Diterima' : '❌ Ditolak' }}
            </span>
          </div>
        </div>

        <!-- Search guild -->
        <div class="guild-search-box">
          <div class="gs-search-row">
            <input
              v-model="guildSearchQuery"
              class="gs-input"
              placeholder="🔍 Cari nama guild..."
              @keyup.enter="searchGuilds"
            />
            <button class="pixel-btn gs-btn" :disabled="guildSearchLoading" @click="searchGuilds">
              {{ guildSearchLoading ? '...' : 'CARI' }}
            </button>
          </div>

          <div v-if="guildSearchResults.length" class="gs-results">
            <div v-for="g in guildSearchResults" :key="g.id" class="gs-row">
              <div class="gs-info">
                <span class="gs-name">⚜ {{ g.name }}</span>
                <span class="gs-meta">Lv.{{ g.level }} · {{ g.players_count }} member</span>
              </div>
              <button
                v-if="!hasPendingRequest(g.id)"
                class="pixel-btn gs-join-btn"
                @click="requestJoinGuild(g.id)"
              >📨 REQUEST JOIN</button>
              <span v-else class="gs-pending-badge">⏳ PENDING</span>
            </div>
          </div>
          <div v-else-if="!guildSearchLoading" class="gs-empty">
            Tidak ada guild ditemukan. Coba kata kunci lain.
          </div>
        </div>

        <!-- Buat guild baru dari sini -->
        <div class="no-guild-create">
          <div class="ngc-label">— atau —</div>
          <button class="pixel-btn ngc-btn" @click="showCreateGuildModal = true">⚜ BUAT GUILD BARU</button>
        </div>
      </div>

      <!-- LOADING -->
      <div v-if="loading" class="loading-dungeon">
        <div class="load-skull">☠</div>
        <div class="load-dots"><span></span><span></span><span></span></div>
        <p class="load-text">ENTERING DUNGEON...</p>
      </div>

      <!-- BOARD VIEW -->
      <main v-else-if="activeView === 'board'" class="board-view">
        <div
          v-for="col in columns"
          :key="col.key"
          class="dungeon-col"
          :style="{ '--col-color': col.color, '--col-glow': col.glow }"
          @dragover="onDragOver"
          @drop="onDrop($event, col.key)"
        >
          <div class="col-head">
            <div class="col-head-inner">
              <span class="col-ico">{{ col.icon }}</span>
              <span class="col-lbl">{{ col.label }}</span>
            </div>
            <span class="col-badge">{{ questsByStatus(col.key).length }}</span>
          </div>

          <div class="col-body">
            <div v-if="questsByStatus(col.key).length === 0" class="col-empty">
              <span class="empty-icon">⬛</span>
              <span>Drop quest here</span>
            </div>

            <div
              v-for="quest in questsByStatus(col.key)"
              :key="quest.id"
              class="quest-tile"
              :class="{ 'is-dragging': draggingId === quest.id }"
              draggable="true"
              @dragstart="onDragStart($event, quest)"
              @dragend="draggingId = null"
            >
              <div class="tile-top">
                <span class="rank-chip" :style="{ color: rankConfig[quest.rank].color, boxShadow: rankConfig[quest.rank].glow }">{{ quest.rank }}</span>
                <span v-if="quest.doomsday" class="doom-label">⏳{{ quest.doomsday }}</span>
              </div>

              <h3 class="tile-title">{{ quest.title }}</h3>
              <p v-if="quest.description" class="tile-desc">{{ quest.description }}</p>

              <div v-if="quest.player" class="tile-assignee">
                <span class="assign-dot" :style="{ background: classConfig[quest.player.class]?.color || '#9e9e9e' }"></span>
                <span class="assign-name">{{ quest.player.name }}</span>
                <span class="assign-class">[{{ quest.player.class }}]</span>
              </div>
              <div v-else class="tile-assignee unassigned">
                <span class="assign-dot" style="background:#444"></span>
                <span class="assign-name">Unassigned</span>
              </div>

              <div class="tile-rewards">
                <span class="reward-chip exp-chip">⭐ {{ quest.reward_exp }}</span>
                <span class="reward-chip gold-chip">💰 {{ quest.reward_gold }}</span>
              </div>

              <div class="tile-actions">
                <div class="move-row">
                  <template v-for="c in columns" :key="c.key">
                    <button
                      v-if="c.key !== quest.status"
                      class="btn-move"
                      :style="{ '--mc': c.color }"
                      @click="moveQuest(quest.id, c.key)"
                      :title="c.label"
                    >{{ c.icon }}</button>
                  </template>
                </div>
                <div class="edit-row">
                  <button class="btn-act edit" @click="openEditQuest(quest)" title="Edit">✏</button>
                  <button
                    class="btn-act del"
                    @click="deleteQuest(quest.id)"
                    :title="isGM ? 'Hapus' : 'Hanya GM'"
                    :style="!isGM ? { opacity: 0.3, cursor: 'not-allowed' } : {}"
                  >✕</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <!-- LEADERBOARD VIEW (within-guild) -->
      <main v-else-if="activeView === 'leaderboard'" class="leaderboard-view">
        <div class="lb-header">
          <h2 class="lb-title">🏆 GUILD LEADERBOARD</h2>
          <p class="lb-sub">TOP ADVENTURERS OF {{ (guildInfo.name||'').toUpperCase() }}</p>
        </div>
        <div class="lb-list">
          <div
            v-for="(player, idx) in sortedLeaderboard"
            :key="player.id"
            class="lb-row"
            :class="{ 'rank-1': idx===0, 'rank-2': idx===1, 'rank-3': idx===2, 'is-me': player.id === myPlayer?.id }"
          >
            <div class="lb-rank-num">
              <span v-if="idx===0">👑</span>
              <span v-else-if="idx===1">🥈</span>
              <span v-else-if="idx===2">🥉</span>
              <span v-else class="rank-num">#{{ idx+1 }}</span>
            </div>
            <div class="lb-avatar" v-html="pixelAvatar(player.class)"></div>
            <div class="lb-info">
              <div class="lb-name-row">
                <span class="lb-name">{{ player.name }}</span>
                <span class="lb-class" :style="{ color: classConfig[player.class]?.color }">{{ player.class }}</span>
                <span v-if="player.role === 'guild_master'" class="gm-badge">👑 GM</span>
                <span v-if="player.id === myPlayer?.id" class="me-badge">YOU</span>
              </div>
              <div class="lb-stats-row">
                <span class="lb-stat">LV.{{ player.level }}</span>
                <span class="lb-stat">{{ player.quests_cleared }} Quests</span>
                <span class="lb-stat" v-if="player.streak > 0">🔥 {{ player.streak }} Streak</span>
              </div>
              <div class="lb-achievements" v-if="player.achievements?.length">
                <span v-for="ach in player.achievements.slice(0,4)" :key="ach.id" class="ach-badge" :title="ach.desc">{{ ach.icon }}</span>
                <span v-if="player.achievements.length > 4" class="ach-more">+{{ player.achievements.length - 4 }}</span>
              </div>
            </div>
            <div class="lb-score">
              <span class="score-val gold-glow">{{ player.quests_cleared }}</span>
              <span class="score-lbl">CLEARED</span>
              <span class="score-gold">💰{{ (player.gold||0).toLocaleString() }}</span>
            </div>
          </div>
          <div v-if="sortedLeaderboard.length === 0" class="lb-empty">
            <p>☠ Belum ada adventurer terdaftar.</p>
          </div>
        </div>
      </main>

      <!-- PARTY VIEW -->
      <main v-else-if="activeView === 'players'" class="players-view">
        <div class="pv-header">
          <h2 class="pv-title">⚔️ PARTY MEMBERS</h2>
          <div class="pv-header-right">
            <div v-if="guildInfo.invite_code" class="invite-code-box">
              <span class="invite-label">INVITE CODE</span>
              <span class="invite-code">{{ guildInfo.invite_code }}</span>
            </div>
          </div>
        </div>

        <!-- GM: Join Requests Panel -->
        <div v-if="isGM" class="join-requests-panel">
          <div class="jrp-header" @click="joinRequestsLoading || fetchJoinRequests()">
            <span class="jrp-title">📨 JOIN REQUESTS</span>
            <span v-if="joinRequests.length" class="jrp-count">{{ joinRequests.length }} pending</span>
            <span v-else class="jrp-empty-label">Tidak ada request baru</span>
          </div>
          <div v-if="joinRequests.length" class="jrp-list">
            <div v-for="req in joinRequests" :key="req.id" class="jrp-row">
              <div v-html="pixelAvatar(req.player.class)" style="transform:scale(0.5);flex-shrink:0"></div>
              <div class="jrp-info">
                <span class="jrp-name">{{ req.player.name }}</span>
                <span class="jrp-meta">{{ req.player.class }} · Lv.{{ req.player.level }} · {{ req.player.quests_cleared }} quest cleared</span>
              </div>
              <div class="jrp-actions">
                <button class="pixel-btn jrp-accept" @click="acceptJoinRequest(req.id, req.player.name)">✅ TERIMA</button>
                <button class="pixel-btn jrp-reject" @click="rejectJoinRequest(req.id, req.player.name)">❌ TOLAK</button>
              </div>
            </div>
          </div>
        </div>

        <div class="player-grid">
          <div v-for="player in playersList" :key="player.id" class="player-card" :class="{ 'is-me': player.id === myPlayer?.id }">
            <div class="pc-header" :style="{ borderColor: classConfig[player.class]?.color || '#9e9e9e' }">
              <div class="pc-avatar" v-html="pixelAvatar(player.class)"></div>
              <div class="pc-identity">
                <div class="pc-name-row">
                  <span class="pc-name">{{ player.name }}</span>
                  <span v-if="player.role === 'guild_master'" class="pc-gm-badge">👑 GM</span>
                  <span v-if="player.id === myPlayer?.id" class="pc-me-badge">YOU</span>
                </div>
                <span class="pc-class" :style="{ color: classConfig[player.class]?.color }">
                  {{ classConfig[player.class]?.icon }} {{ player.class }}
                </span>
              </div>
              <div class="pc-lv">
                <span class="pc-lv-num">{{ player.level }}</span>
                <span class="pc-lv-lbl">LV</span>
              </div>
            </div>

            <div class="pc-stats">
              <div class="pc-stat-row">
                <span class="pc-stat-label">EXP</span>
                <div class="pc-bar-track">
                  <div class="pc-bar-fill exp-bar" :style="{ width: Math.min(100, Math.round((player.exp / (player.level * 500)) * 100)) + '%' }"></div>
                </div>
                <span class="pc-stat-val">{{ player.exp }}</span>
              </div>
              <div class="pc-stat-row">
                <span class="pc-stat-label">GOLD</span>
                <span class="pc-stat-val gold-glow">{{ (player.gold||0).toLocaleString() }}</span>
              </div>
              <div class="pc-stat-row">
                <span class="pc-stat-label">CLEARED</span>
                <span class="pc-stat-val">{{ player.quests_cleared }}</span>
              </div>
              <div class="pc-stat-row">
                <span class="pc-stat-label">STREAK</span>
                <span class="pc-stat-val" :style="{ color: player.streak > 0 ? '#ef4444' : '' }">
                  {{ player.streak > 0 ? '🔥' : '' }}{{ player.streak }} <span class="streak-best">(best: {{ player.best_streak }})</span>
                </span>
              </div>
            </div>

            <div class="pc-quests">
              <span class="pc-q-label">ACTIVE QUESTS</span>
              <div class="pc-q-list">
                <div v-for="q in questsList.filter(q => q.player_id === player.id && q.status !== 'cleared')" :key="q.id" class="pc-q-item">
                  <span class="pc-q-rank" :style="{ color: rankConfig[q.rank].color }">{{ q.rank }}</span>
                  <span class="pc-q-title">{{ q.title }}</span>
                  <span class="pc-q-status" :style="{ color: columns.find(c=>c.key===q.status)?.color }">{{ q.status.replace('_',' ') }}</span>
                </div>
                <span v-if="!questsList.some(q => q.player_id === player.id && q.status !== 'cleared')" class="pc-q-none">— Idle —</span>
              </div>
            </div>

            <div class="pc-achievements" v-if="player.achievements?.length">
              <span class="pc-q-label">ACHIEVEMENTS</span>
              <div class="ach-grid">
                <div v-for="ach in player.achievements" :key="ach.id" class="ach-item" :title="ach.desc">
                  <span class="ach-icon">{{ ach.icon }}</span>
                  <span class="ach-name">{{ ach.name }}</span>
                </div>
              </div>
            </div>
            <div class="pc-no-ach" v-else>
              <span class="pc-q-label">No achievements yet</span>
            </div>

            <!-- Kick button — hanya GM, dan tidak bisa kick diri sendiri / GM lain -->
            <button
              v-if="isGM && player.role !== 'guild_master'"
              class="btn-kick"
              @click="deletePlayer(player.id)"
            >KICK FROM GUILD</button>
          </div>
        </div>

        <div v-if="playersList.length === 0" class="empty-party">
          <p>☠ Guild kosong.</p>
        </div>
      </main>

      <!-- GUILD LEADERBOARD (antar guild) -->
      <main v-else-if="activeView === 'guild_lb'" class="leaderboard-view">
        <div class="lb-header">
          <h2 class="lb-title">🌐 SERVER LEADERBOARD</h2>
          <p class="lb-sub">SEMUA GUILD — RANKED BY QUEST CLEARED</p>
        </div>
        <div class="lb-list">
          <div
            v-for="(guild, idx) in guildLeaderboard"
            :key="guild.id"
            class="lb-row guild-lb-row"
            :class="{ 'rank-1': idx===0, 'rank-2': idx===1, 'rank-3': idx===2, 'is-me': guild.id === guildInfo.id }"
          >
            <div class="lb-rank-num">
              <span v-if="idx===0">👑</span>
              <span v-else-if="idx===1">🥈</span>
              <span v-else-if="idx===2">🥉</span>
              <span v-else class="rank-num">#{{ idx+1 }}</span>
            </div>
            <div class="guild-lb-icon">⚜</div>
            <div class="lb-info">
              <div class="lb-name-row">
                <span class="lb-name">{{ guild.name }}</span>
                <span v-if="guild.id === guildInfo.id" class="me-badge">MY GUILD</span>
              </div>
              <div class="lb-stats-row">
                <span class="lb-stat">LV.{{ guild.level }}</span>
                <span class="lb-stat">{{ guild.players_count }} Members</span>
                <span class="lb-stat">{{ guild.total_cleared }} Quests Cleared</span>
              </div>
              <div class="lb-stats-row">
                <span class="lb-stat muted-stat">Invite: <strong>{{ guild.invite_code }}</strong></span>
              </div>
            </div>
            <div class="lb-score">
              <span class="score-val gold-glow">{{ guild.total_cleared }}</span>
              <span class="score-lbl">CLEARED</span>
              <span class="score-gold">💰{{ (guild.gold||0).toLocaleString() }}</span>
            </div>
          </div>
          <div v-if="guildLeaderboard.length === 0" class="lb-empty">
            <p>☠ Belum ada guild terdaftar.</p>
          </div>
        </div>
      </main>

      <!-- MODAL: QUEST -->
      <Transition name="modal">
        <div v-if="showQuestModal" class="modal-veil" @click.self="closeQuestModal">
          <div class="dungeon-modal">
            <div class="dm-head">
              <span>{{ isEditing ? '✏️ EDIT QUEST' : '📜 POST QUEST' }}</span>
              <button class="dm-close" @click="closeQuestModal">✕</button>
            </div>
            <div class="dm-body">
              <div class="field"><label>QUEST TITLE *</label>
                <input v-model="questForm.title" placeholder="Nama misi..." />
              </div>
              <div class="field"><label>DESCRIPTION</label>
                <textarea v-model="questForm.description" rows="3" placeholder="Detail misi..."></textarea>
              </div>
              <div class="field-row">
                <div class="field"><label>RANK *</label>
                  <select v-model="questForm.rank">
                    <option value="S">S — Legendary</option>
                    <option value="A">A — Epic</option>
                    <option value="B">B — Rare</option>
                    <option value="C">C — Common</option>
                  </select>
                </div>
                <div class="field"><label>ASSIGN TO</label>
                  <select v-model="questForm.player_id">
                    <option value="">— Unassigned —</option>
                    <option v-for="p in playersList" :key="p.id" :value="p.id">{{ p.name }} [{{ p.class }}]</option>
                  </select>
                </div>
              </div>
              <div class="field-row">
                <div class="field"><label>REWARD EXP</label>
                  <input v-model.number="questForm.reward_exp" type="number" min="0" />
                </div>
                <div class="field"><label>REWARD GOLD</label>
                  <input v-model.number="questForm.reward_gold" type="number" min="0" />
                </div>
              </div>
              <div class="field"><label>DEADLINE</label>
                <input v-model="questForm.doomsday" placeholder="e.g. 3 Days Left" />
              </div>
            </div>
            <div class="dm-foot">
              <button class="pixel-btn ghost" @click="closeQuestModal">CANCEL</button>
              <button class="pixel-btn" @click="submitQuestForm">{{ isEditing ? 'SAVE' : 'POST' }}</button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Modal: Buat Guild Baru (dari no-guild screen) -->
      <Transition name="modal">
        <div v-if="showCreateGuildModal" class="modal-veil" @click.self="showCreateGuildModal = false">
          <div class="dungeon-modal narrow">
            <div class="dm-head">
              <span>⚜ BUAT GUILD BARU</span>
              <button class="dm-close" @click="showCreateGuildModal = false">✕</button>
            </div>
            <div class="dm-body">
              <div class="field">
                <label>NAMA GUILD</label>
                <input v-model="createGuildForm.guild_name" placeholder="Shadow Dragons, Iron Wolves..." maxlength="100" @keyup.enter="createGuildFromApp" />
              </div>
              <div class="auth-guild-info">
                <span>⭐ Kamu akan jadi <strong>Guild Master</strong></span>
                <span>🔑 Invite code akan dibuat otomatis</span>
              </div>
            </div>
            <div class="dm-foot">
              <button class="pixel-btn ghost" @click="showCreateGuildModal = false">BATAL</button>
              <button class="pixel-btn" :disabled="createGuildLoading" @click="createGuildFromApp">
                {{ createGuildLoading ? 'LOADING...' : '⚜ BUAT' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </template>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #0a0a0f;
  --bg2:      #0f0f18;
  --panel:    #12121e;
  --panel2:   #1a1a2e;
  --border:   #2a2a4a;
  --border2:  #3a3a5a;
  --text:     #c8c8e8;
  --muted:    #5a5a8a;
  --gold:     #ffd700;
  --gold-dim: rgba(255,215,0,0.15);
  --purple:   #7c6af7;
  --red:      #ef4444;
  --green:    #22c55e;
  --pixel:    'Press Start 2P', monospace;
  --vt:       'VT323', monospace;
}

html, body { background: var(--bg); color: var(--text); }
body { font-family: var(--vt); font-size: 18px; overflow-x: hidden; }

.scanlines {
  position: fixed; inset: 0; z-index: 9998; pointer-events: none;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.08) 2px, rgba(0,0,0,0.08) 4px);
}

.dungeon-app {
  min-height: 100vh; background: var(--bg);
  background-image:
    radial-gradient(ellipse at 10% 0%, rgba(124,106,247,0.1) 0%, transparent 50%),
    radial-gradient(ellipse at 90% 100%, rgba(239,68,68,0.07) 0%, transparent 50%);
}

/* ── Toast ─────────────────────────────────────────────────── */
.toast-notif {
  position: fixed; top: 1.2rem; left: 50%; transform: translateX(-50%);
  background: var(--panel2); border: 2px solid var(--border2);
  padding: .6rem 1.4rem; z-index: 9999;
  font-family: var(--pixel); font-size: .55rem; letter-spacing: .05em;
  color: var(--green); box-shadow: 0 0 20px rgba(34,197,94,0.3), inset 0 0 20px rgba(0,0,0,0.5);
  white-space: nowrap;
}
.toast-notif.error { color: var(--red); box-shadow: 0 0 20px rgba(239,68,68,0.3), inset 0 0 20px rgba(0,0,0,0.5); border-color: var(--red); }
.toast-enter-active, .toast-leave-active { transition: all .3s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-10px); }

/* ── AUTH SCREEN ───────────────────────────────────────────── */
.auth-screen {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden; padding: 1rem;
}
.auth-bg-grid {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(124,106,247,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(124,106,247,0.04) 1px, transparent 1px);
  background-size: 40px 40px;
}
.auth-container {
  position: relative; z-index: 1; width: 100%; max-width: 440px;
  display: flex; flex-direction: column; align-items: center; gap: 1.5rem;
}
.auth-logo { text-align: center; }
.auth-logo-icon { font-size: 3rem; color: var(--gold); text-shadow: 0 0 30px rgba(255,215,0,0.6); }
.auth-logo-text { font-family: var(--pixel); font-size: .7rem; color: var(--gold); letter-spacing: .1em; margin-top: .4rem; }
.auth-logo-sub  { font-family: var(--pixel); font-size: .4rem; color: var(--muted); letter-spacing: .15em; margin-top: .3rem; }

.auth-panel {
  width: 100%; background: var(--panel);
  border: 2px solid var(--border2);
  box-shadow: 0 0 40px rgba(0,0,0,.8), 0 0 80px rgba(124,106,247,0.07);
  padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;
}
.auth-panel-title {
  font-family: var(--pixel); font-size: .55rem; color: var(--gold);
  letter-spacing: .08em; text-align: center; padding-bottom: .5rem;
  border-bottom: 1px solid var(--border);
}
.auth-step-dots { display: flex; justify-content: center; gap: .5rem; }
.dot { width: 8px; height: 8px; background: var(--border2); transition: background .2s; }
.dot.active { background: var(--purple); box-shadow: 0 0 8px rgba(124,106,247,0.6); }

.auth-fields { display: flex; flex-direction: column; gap: .7rem; }
.auth-field { display: flex; flex-direction: column; gap: .3rem; }
.auth-field label {
  font-family: var(--pixel); font-size: .38rem; color: var(--muted); letter-spacing: .1em;
}
.auth-field input {
  background: var(--panel2); border: 1px solid var(--border2);
  color: var(--text); padding: .6rem .8rem;
  font-family: var(--vt); font-size: 1.1rem;
  outline: none; transition: border-color .2s; width: 100%;
}
.auth-field input:focus { border-color: var(--purple); box-shadow: 0 0 0 2px rgba(124,106,247,0.15); }
.auth-error {
  font-family: var(--pixel); font-size: .4rem; color: var(--red);
  background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3);
  padding: .5rem; line-height: 1.6;
}
.auth-btn { width: 100%; justify-content: center; }
.auth-switch { text-align: center; font-size: .95rem; color: var(--muted); }
.auth-link {
  background: none; border: none; color: var(--purple); cursor: pointer;
  font-family: var(--pixel); font-size: .4rem; text-decoration: underline;
  margin-left: .3rem;
}

/* Class picker in register */
.auth-class-preview { display: flex; flex-direction: column; align-items: center; gap: .3rem; }
.auth-big-avatar svg { width: 64px !important; height: 64px !important; image-rendering: pixelated; }
.auth-class-name { font-family: var(--pixel); font-size: .5rem; }
.auth-class-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.auth-cls-btn {
  font-family: var(--pixel); font-size: .4rem; letter-spacing: .05em;
  padding: .5rem; background: var(--panel2);
  border: 2px solid var(--border); color: var(--muted);
  cursor: pointer; transition: all .15s; text-align: center;
  display: flex; flex-direction: column; align-items: center;
}
.auth-cls-btn:hover, .auth-cls-btn.selected { background: rgba(255,255,255,.04); }

/* Guild step */
.auth-guild-tabs { display: flex; gap: .4rem; }
.auth-tab {
  flex: 1; font-family: var(--pixel); font-size: .4rem; letter-spacing: .05em;
  padding: .5rem; background: var(--panel2);
  border: 2px solid var(--border); color: var(--muted);
  cursor: pointer; transition: all .2s;
}
.auth-tab.active, .auth-tab:hover { border-color: var(--purple); color: var(--text); background: rgba(124,106,247,0.1); }
.auth-guild-info {
  display: flex; flex-direction: column; gap: .4rem;
  background: var(--panel2); border: 1px solid var(--border);
  padding: .8rem; font-size: .95rem; color: var(--muted);
}
.auth-guild-info strong { color: var(--gold); }
.auth-nav-row { display: flex; gap: .6rem; }
.auth-nav-row .pixel-btn { flex: 1; justify-content: center; }

/* ── Pixel Button ──────────────────────────────────────────── */
.pixel-btn {
  font-family: var(--pixel); font-size: .5rem; letter-spacing: .05em;
  padding: .6rem 1rem; background: var(--purple);
  border: 2px solid #a78bfa; color: #fff; cursor: pointer;
  box-shadow: 3px 3px 0 #3b2f8f, 0 0 16px rgba(124,106,247,0.4);
  transition: all .1s; white-space: nowrap; display: inline-flex; align-items: center;
}
.pixel-btn:hover:not(:disabled) { transform: translate(-1px,-1px); box-shadow: 4px 4px 0 #3b2f8f, 0 0 24px rgba(124,106,247,0.6); }
.pixel-btn:active:not(:disabled) { transform: translate(2px,2px); box-shadow: 1px 1px 0 #3b2f8f; }
.pixel-btn.ghost    { background: transparent; border-color: var(--border2); color: var(--muted); box-shadow: 3px 3px 0 #111; }
.pixel-btn.ghost:hover:not(:disabled) { border-color: var(--text); color: var(--text); }
.pixel-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── Navbar ────────────────────────────────────────────────── */
.dungeon-nav {
  display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
  padding: .6rem 1.2rem;
  background: var(--panel);
  border-bottom: 2px solid var(--border2);
  box-shadow: 0 4px 24px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,215,0,0.05);
}
.nav-brand { display: flex; align-items: center; gap: .75rem; }
.brand-icon { font-size: 1.5rem; color: var(--gold); text-shadow: 0 0 16px rgba(255,215,0,0.6); flex-shrink:0; }
.brand-text { display: flex; flex-direction: column; }
.brand-name { font-family: var(--pixel); font-size: .55rem; color: var(--gold); letter-spacing: .05em; }
.brand-sub  { font-size: .85rem; color: var(--muted); letter-spacing: .1em; }

.nav-guild-stats { display: flex; align-items: center; gap: 1rem; flex: 1; }
.guild-stat { display: flex; flex-direction: column; align-items: center; gap: .1rem; }
.gstat-label { font-family: var(--pixel); font-size: .4rem; color: var(--muted); letter-spacing: .1em; }
.gstat-val   { font-family: var(--pixel); font-size: .65rem; }
.gold-glow   { color: var(--gold); text-shadow: 0 0 10px rgba(255,215,0,0.5); }
.exp-track { display: flex; flex-direction: column; gap: .2rem; min-width: 90px; }
.exp-label-row { display: flex; justify-content: space-between; }
.exp-rail  { height: 5px; background: var(--panel2); border: 1px solid var(--border); }
.exp-fill  { height: 100%; background: linear-gradient(90deg, var(--purple), #a78bfa); box-shadow: 0 0 8px rgba(124,106,247,0.6); transition: width .6s ease; }

.nav-tabs { display: flex; gap: .3rem; }
.nav-tab {
  font-family: var(--pixel); font-size: .4rem; letter-spacing: .05em;
  padding: .4rem .6rem; background: var(--panel2);
  border: 2px solid var(--border); color: var(--muted); cursor: pointer; transition: all .2s;
}
.nav-tab:hover, .nav-tab.active { border-color: var(--purple); color: var(--text); box-shadow: 0 0 12px rgba(124,106,247,0.4); }
.nav-tab.active { background: rgba(124,106,247,0.15); color: #a78bfa; }

/* Profile + Logout */
.nav-profile { display: flex; align-items: center; gap: .5rem; margin-left: auto; }
.nav-me {
  display: flex; align-items: center; gap: .3rem;
  border: 1px solid; padding: .2rem .5rem;
  background: var(--panel2);
}
.nav-me-info { display: flex; flex-direction: column; }
.nav-me-name { font-family: var(--pixel); font-size: .38rem; color: var(--text); }
.nav-me-role { font-family: var(--pixel); font-size: .35rem; }
.nav-logout { font-size: .4rem !important; padding: .4rem .6rem !important; }

.btn-post-quest { font-size: .45rem !important; }

/* ── Loading ───────────────────────────────────────────────── */
.loading-dungeon { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60vh; gap: 1rem; }
.load-skull { font-size: 4rem; animation: pulse 1s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
.load-dots { display: flex; gap: .5rem; }
.load-dots span { width: 8px; height: 8px; background: var(--purple); animation: blink 1s infinite; }
.load-dots span:nth-child(2) { animation-delay: .2s; }
.load-dots span:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }
.load-text { font-family: var(--pixel); font-size: .6rem; color: var(--muted); letter-spacing: .2em; animation: blink 1.5s infinite; }

/* ── Board View ────────────────────────────────────────────── */
.board-view { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; padding: 1rem; min-height: calc(100vh - 80px); }
@media(max-width:1100px) { .board-view { grid-template-columns: repeat(2,1fr); } }
@media(max-width:640px)  { .board-view { grid-template-columns: 1fr; } }

.dungeon-col { display: flex; flex-direction: column; background: var(--panel); border: 2px solid var(--border); border-top: 3px solid var(--col-color); box-shadow: 0 0 20px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.02); transition: box-shadow .2s; }
.dungeon-col:hover { box-shadow: 0 0 30px rgba(0,0,0,0.6), 0 0 8px var(--col-glow); }
.col-head { display: flex; align-items: center; justify-content: space-between; padding: .6rem .8rem; background: var(--panel2); border-bottom: 1px solid var(--border); }
.col-head-inner { display: flex; align-items: center; gap: .5rem; }
.col-ico { font-size: 1rem; }
.col-lbl { font-family: var(--pixel); font-size: .45rem; color: var(--col-color); letter-spacing: .08em; text-shadow: 0 0 8px var(--col-color); }
.col-badge { font-family: var(--pixel); font-size: .45rem; background: var(--col-color); color: #000; padding: .15rem .35rem; min-width: 1.2rem; text-align: center; }
.col-body { flex: 1; padding: .6rem; display: flex; flex-direction: column; gap: .6rem; min-height: 100px; }
.col-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; border: 2px dashed var(--border); padding: 2rem; color: var(--muted); font-size: .9rem; text-align: center; }
.empty-icon { font-size: 1.5rem; opacity: .3; }

/* ── Quest Tile ────────────────────────────────────────────── */
.quest-tile { background: var(--panel2); border: 1px solid var(--border); border-left: 3px solid var(--border2); padding: .7rem; cursor: grab; transition: transform .15s, border-color .15s, box-shadow .15s; user-select: none; }
.quest-tile:hover { transform: translateY(-2px); border-left-color: var(--purple); box-shadow: 0 4px 16px rgba(0,0,0,0.5); }
.quest-tile.is-dragging { opacity: .4; cursor: grabbing; }
.tile-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
.rank-chip { font-family: var(--pixel); font-size: .55rem; font-weight: 700; border: 1px solid currentColor; padding: .1rem .4rem; }
.doom-label { font-size: .85rem; color: var(--red); font-style: italic; }
.tile-title { font-family: var(--pixel); font-size: .5rem; line-height: 1.6; color: var(--text); margin-bottom: .4rem; word-break: break-word; }
.tile-desc { font-size: .95rem; color: var(--muted); line-height: 1.4; margin-bottom: .4rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.tile-assignee { display: flex; align-items: center; gap: .4rem; margin-bottom: .4rem; font-size: .85rem; }
.tile-assignee.unassigned { opacity: .4; }
.assign-dot { width: 6px; height: 6px; border-radius: 0; flex-shrink: 0; }
.assign-name { color: var(--text); }
.assign-class { color: var(--muted); font-size: .8rem; }
.tile-rewards { display: flex; gap: .4rem; margin-bottom: .5rem; flex-wrap: wrap; }
.reward-chip { font-family: var(--pixel); font-size: .38rem; padding: .2rem .4rem; border: 1px solid; }
.exp-chip  { color: #a78bfa; border-color: #a78bfa44; background: rgba(167,139,250,.08); }
.gold-chip { color: var(--gold); border-color: #ffd70044; background: var(--gold-dim); }
.tile-actions { display: flex; align-items: center; justify-content: space-between; gap: .3rem; border-top: 1px solid var(--border); padding-top: .4rem; }
.move-row { display: flex; gap: .25rem; flex-wrap: wrap; flex: 1; }
.edit-row { display: flex; gap: .25rem; }
.btn-move { font-size: .75rem; padding: .15rem .35rem; background: rgba(255,255,255,.03); border: 1px solid color-mix(in srgb, var(--mc) 40%, transparent); color: var(--mc); cursor: pointer; transition: all .15s; }
.btn-move:hover { background: color-mix(in srgb, var(--mc) 20%, transparent); }
.btn-act { font-family: var(--pixel); font-size: .38rem; padding: .2rem .4rem; border: 1px solid var(--border2); background: var(--panel); color: var(--muted); cursor: pointer; transition: all .15s; }
.btn-act.edit:hover { border-color: var(--purple); color: #a78bfa; background: rgba(124,106,247,.15); }
.btn-act.del:hover  { border-color: var(--red); color: var(--red); background: rgba(239,68,68,.15); }

/* ── Leaderboard ───────────────────────────────────────────── */
.leaderboard-view { padding: 1.5rem; max-width: 900px; margin: 0 auto; }
.lb-header { text-align: center; margin-bottom: 1.5rem; }
.lb-title { font-family: var(--pixel); font-size: .9rem; color: var(--gold); text-shadow: 0 0 20px rgba(255,215,0,.5); letter-spacing: .1em; }
.lb-sub   { font-size: 1rem; color: var(--muted); margin-top: .3rem; letter-spacing: .2em; }
.lb-list { display: flex; flex-direction: column; gap: .75rem; }
.lb-row { display: flex; align-items: center; gap: 1rem; background: var(--panel); border: 1px solid var(--border); padding: .8rem 1rem; transition: border-color .2s, box-shadow .2s; }
.lb-row:hover { border-color: var(--border2); box-shadow: 0 0 12px rgba(0,0,0,.5); }
.lb-row.rank-1 { border-color: var(--gold); box-shadow: 0 0 20px rgba(255,215,0,.2); }
.lb-row.rank-2 { border-color: #aaa; }
.lb-row.rank-3 { border-color: #cd7f32; }
.lb-row.is-me  { border-color: var(--purple); box-shadow: 0 0 20px rgba(124,106,247,.2); }
.lb-rank-num { font-family: var(--pixel); font-size: .65rem; min-width: 2rem; text-align: center; color: var(--muted); }
.rank-num { color: var(--muted); }
.lb-avatar { flex-shrink: 0; image-rendering: pixelated; }
.lb-info { flex: 1; display: flex; flex-direction: column; gap: .3rem; }
.lb-name-row { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.lb-name  { font-family: var(--pixel); font-size: .55rem; color: var(--text); }
.lb-class { font-size: .9rem; }
.lb-stats-row { display: flex; gap: .8rem; flex-wrap: wrap; }
.lb-stat { font-size: .9rem; color: var(--muted); }
.muted-stat { color: var(--border2); }
.lb-stat strong { color: var(--text); }
.lb-achievements { display: flex; gap: .3rem; align-items: center; flex-wrap: wrap; }
.ach-badge { font-size: 1rem; cursor: help; }
.ach-more  { font-size: .8rem; color: var(--muted); }
.lb-score { display: flex; flex-direction: column; align-items: flex-end; gap: .15rem; }
.score-val  { font-family: var(--pixel); font-size: .85rem; }
.score-lbl  { font-family: var(--pixel); font-size: .32rem; color: var(--muted); letter-spacing: .1em; }
.score-gold { font-size: .85rem; color: var(--gold); }
.lb-empty { text-align: center; padding: 3rem; color: var(--muted); font-family: var(--pixel); font-size: .6rem; }

/* Badges */
.gm-badge, .me-badge, .pc-gm-badge, .pc-me-badge {
  font-family: var(--pixel); font-size: .32rem; padding: .15rem .35rem; border: 1px solid;
}
.gm-badge, .pc-gm-badge { color: var(--gold); border-color: var(--gold); background: rgba(255,215,0,.08); }
.me-badge, .pc-me-badge { color: var(--purple); border-color: var(--purple); background: rgba(124,106,247,.08); }

/* Guild LB */
.guild-lb-row .guild-lb-icon { font-size: 2rem; color: var(--gold); opacity: .6; }

/* ── Party / Players ───────────────────────────────────────── */
.players-view { padding: 1.5rem; }
.pv-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; flex-wrap: wrap; gap: .5rem; }
.pv-title { font-family: var(--pixel); font-size: .7rem; color: var(--text); letter-spacing: .08em; }
.pv-header-right { display: flex; align-items: center; gap: 1rem; }
.invite-code-box { display: flex; align-items: center; gap: .5rem; background: var(--panel); border: 1px solid var(--gold); padding: .4rem .8rem; }
.invite-label { font-family: var(--pixel); font-size: .35rem; color: var(--muted); }
.invite-code  { font-family: var(--pixel); font-size: .6rem; color: var(--gold); text-shadow: 0 0 8px rgba(255,215,0,.5); letter-spacing: .2em; }

.player-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.player-card { background: var(--panel); border: 1px solid var(--border); display: flex; flex-direction: column; gap: 0; overflow: hidden; }
.player-card.is-me { border-color: var(--purple); box-shadow: 0 0 16px rgba(124,106,247,.2); }
.pc-header { display: flex; align-items: center; gap: .75rem; padding: .75rem; background: var(--panel2); border-bottom: 2px solid; }
.pc-avatar { flex-shrink: 0; image-rendering: pixelated; }
.pc-identity { flex: 1; display: flex; flex-direction: column; gap: .2rem; }
.pc-name-row { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.pc-name  { font-family: var(--pixel); font-size: .5rem; color: var(--text); }
.pc-class { font-size: .9rem; }
.pc-lv { display: flex; flex-direction: column; align-items: center; }
.pc-lv-num { font-family: var(--pixel); font-size: .85rem; color: var(--gold); text-shadow: 0 0 8px rgba(255,215,0,.5); }
.pc-lv-lbl { font-family: var(--pixel); font-size: .32rem; color: var(--muted); }
.pc-stats { padding: .6rem .75rem; display: flex; flex-direction: column; gap: .3rem; border-bottom: 1px solid var(--border); }
.pc-stat-row { display: flex; align-items: center; gap: .5rem; font-size: .9rem; }
.pc-stat-label { font-family: var(--pixel); font-size: .38rem; color: var(--muted); min-width: 55px; }
.pc-stat-val   { color: var(--text); }
.streak-best   { color: var(--muted); font-size: .8rem; }
.pc-bar-track  { flex: 1; height: 5px; background: var(--panel2); border: 1px solid var(--border); }
.pc-bar-fill   { height: 100%; transition: width .5s ease; }
.exp-bar       { background: linear-gradient(90deg, var(--purple), #a78bfa); box-shadow: 0 0 6px rgba(124,106,247,.6); }
.pc-quests { padding: .6rem .75rem; border-bottom: 1px solid var(--border); }
.pc-q-label { font-family: var(--pixel); font-size: .35rem; color: var(--muted); display: block; margin-bottom: .4rem; letter-spacing: .1em; }
.pc-q-list  { display: flex; flex-direction: column; gap: .25rem; }
.pc-q-item  { display: flex; align-items: center; gap: .4rem; font-size: .85rem; }
.pc-q-rank  { font-family: var(--pixel); font-size: .38rem; }
.pc-q-title { flex: 1; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-q-status{ font-size: .75rem; text-transform: uppercase; }
.pc-q-none  { color: var(--muted); font-size: .85rem; font-style: italic; }
.pc-achievements { padding: .6rem .75rem; border-bottom: 1px solid var(--border); }
.ach-grid { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .3rem; }
.ach-item { display: flex; align-items: center; gap: .3rem; background: var(--panel2); border: 1px solid var(--border2); padding: .2rem .5rem; cursor: help; }
.ach-icon { font-size: .9rem; }
.ach-name { font-size: .75rem; color: var(--muted); }
.pc-no-ach { padding: .5rem .75rem; }
.btn-kick { font-family: var(--pixel); font-size: .38rem; letter-spacing: .05em; width: 100%; padding: .5rem; background: rgba(239,68,68,.08); border: none; border-top: 1px solid rgba(239,68,68,.2); color: #ef444488; cursor: pointer; transition: all .2s; }
.btn-kick:hover { background: rgba(239,68,68,.2); color: var(--red); }
.empty-party { text-align: center; padding: 4rem; color: var(--muted); font-family: var(--pixel); font-size: .6rem; }

/* ── Modal ─────────────────────────────────────────────────── */
.modal-veil { position: fixed; inset: 0; background: rgba(0,0,0,.8); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.dungeon-modal { background: var(--panel); border: 2px solid var(--border2); width: 100%; max-width: 540px; box-shadow: 0 0 40px rgba(0,0,0,.8), 0 0 0 1px rgba(124,106,247,.1); }
.dungeon-modal.narrow { max-width: 380px; }
.dm-head { display: flex; justify-content: space-between; align-items: center; padding: .8rem 1rem; background: var(--panel2); border-bottom: 2px solid var(--border2); font-family: var(--pixel); font-size: .5rem; color: var(--gold); letter-spacing: .08em; }
.dm-close { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1rem; font-family: var(--pixel); transition: color .2s; }
.dm-close:hover { color: var(--red); }
.dm-body { padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-family: var(--pixel); font-size: .38rem; color: var(--muted); letter-spacing: .1em; }
.field input, .field textarea, .field select { background: var(--panel2); border: 1px solid var(--border2); color: var(--text); padding: .5rem .7rem; font-family: var(--vt); font-size: 1rem; outline: none; transition: border-color .2s; resize: none; width: 100%; }
.field input:focus, .field textarea:focus, .field select:focus { border-color: var(--purple); }
.field select option { background: var(--panel2); }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
.dm-foot { display: flex; justify-content: flex-end; gap: .6rem; padding: .8rem 1rem; border-top: 1px solid var(--border); background: var(--panel2); }
.modal-enter-active, .modal-leave-active { transition: all .2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(.96); }

/* ── Notif dot on nav tab ─────────────────────────────────── */
.notif-dot { display: inline-flex; align-items: center; justify-content: center; background: var(--red); color: #fff; font-family: var(--pixel); font-size: .3rem; border-radius: 0; min-width: 1.2em; padding: .15em .3em; margin-left: .3rem; vertical-align: middle; }

/* ── No-Guild Screen ──────────────────────────────────────── */
.no-guild-screen { max-width: 700px; margin: 3rem auto; padding: 0 1rem; display: flex; flex-direction: column; gap: 1.5rem; }
.no-guild-header { text-align: center; }
.no-guild-icon { font-size: 3rem; margin-bottom: .5rem; opacity: .5; }
.no-guild-title { font-family: var(--pixel); font-size: .8rem; color: var(--gold); text-shadow: 0 0 20px rgba(255,215,0,.4); letter-spacing: .1em; margin-bottom: .4rem; }
.no-guild-sub { color: var(--muted); font-size: 1rem; }

/* My join requests status */
.my-requests-box { background: var(--panel); border: 1px solid var(--border2); padding: .8rem 1rem; }
.my-req-title { font-family: var(--pixel); font-size: .42rem; color: var(--muted); margin-bottom: .6rem; letter-spacing: .08em; }
.my-req-row { display: flex; align-items: center; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid var(--border); }
.my-req-row:last-child { border-bottom: none; }
.req-guild-name { font-size: 1rem; color: var(--text); }
.req-status { font-family: var(--pixel); font-size: .35rem; padding: .2rem .5rem; border: 1px solid; }
.req-pending  { color: #f0b429; border-color: #f0b42944; background: rgba(240,180,41,.08); }
.req-accepted { color: #22c55e; border-color: #22c55e44; background: rgba(34,197,94,.08); }
.req-rejected { color: var(--red); border-color: rgba(239,68,68,.3); background: rgba(239,68,68,.08); }

/* Guild Search */
.guild-search-box { background: var(--panel); border: 1px solid var(--border2); }
.gs-search-row { display: flex; gap: .5rem; padding: .8rem; border-bottom: 1px solid var(--border); }
.gs-input { flex: 1; background: var(--panel2); border: 1px solid var(--border2); color: var(--text); padding: .5rem .7rem; font-family: var(--vt); font-size: 1rem; outline: none; transition: border-color .2s; }
.gs-input:focus { border-color: var(--purple); }
.gs-btn { padding: .4rem .9rem; font-size: .4rem; }
.gs-results { display: flex; flex-direction: column; }
.gs-row { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1rem; border-bottom: 1px solid var(--border); gap: .5rem; flex-wrap: wrap; }
.gs-row:last-child { border-bottom: none; }
.gs-row:hover { background: rgba(255,255,255,.02); }
.gs-info { display: flex; flex-direction: column; gap: .2rem; flex: 1; }
.gs-name { font-family: var(--pixel); font-size: .45rem; color: var(--gold); }
.gs-meta { font-size: .85rem; color: var(--muted); }
.gs-join-btn { font-family: var(--pixel); font-size: .35rem; padding: .3rem .7rem; border: 1px solid var(--purple); color: #a78bfa; background: rgba(124,106,247,.1); cursor: pointer; transition: all .2s; }
.gs-join-btn:hover { background: rgba(124,106,247,.25); }
.gs-pending-badge { font-family: var(--pixel); font-size: .35rem; color: #f0b429; border: 1px solid #f0b42944; padding: .3rem .6rem; background: rgba(240,180,41,.08); }
.gs-empty { text-align: center; padding: 2rem; color: var(--muted); font-size: .9rem; }

/* Buat guild dari no-guild */
.no-guild-create { text-align: center; }
.ngc-label { color: var(--muted); font-size: .9rem; margin-bottom: .8rem; }
.ngc-btn { font-size: .45rem; padding: .6rem 1.5rem; border-color: var(--gold); color: var(--gold); background: rgba(255,215,0,.08); }
.ngc-btn:hover { background: rgba(255,215,0,.18); }

/* ── GM Join Requests Panel ───────────────────────────────── */
.join-requests-panel { background: var(--panel); border: 1px solid var(--border2); margin-bottom: 1.5rem; }
.jrp-header { display: flex; align-items: center; gap: .8rem; padding: .6rem 1rem; background: var(--panel2); border-bottom: 1px solid var(--border); cursor: pointer; }
.jrp-title { font-family: var(--pixel); font-size: .45rem; color: var(--gold); letter-spacing: .08em; }
.jrp-count { font-family: var(--pixel); font-size: .38rem; color: var(--red); background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); padding: .15rem .4rem; }
.jrp-empty-label { font-size: .85rem; color: var(--muted); }
.jrp-list { display: flex; flex-direction: column; }
.jrp-row { display: flex; align-items: center; gap: .6rem; padding: .6rem 1rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.jrp-row:last-child { border-bottom: none; }
.jrp-info { flex: 1; display: flex; flex-direction: column; gap: .2rem; }
.jrp-name { font-family: var(--pixel); font-size: .45rem; color: var(--text); }
.jrp-meta { font-size: .85rem; color: var(--muted); }
.jrp-actions { display: flex; gap: .4rem; }
.jrp-accept { font-family: var(--pixel); font-size: .35rem; padding: .25rem .6rem; border: 1px solid #22c55e44; color: #22c55e; background: rgba(34,197,94,.08); cursor: pointer; transition: all .2s; }
.jrp-accept:hover { background: rgba(34,197,94,.2); }
.jrp-reject { font-family: var(--pixel); font-size: .35rem; padding: .25rem .6rem; border: 1px solid rgba(239,68,68,.3); color: var(--red); background: rgba(239,68,68,.08); cursor: pointer; transition: all .2s; }
.jrp-reject:hover { background: rgba(239,68,68,.2); }
</style>