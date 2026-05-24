<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const API = 'http://127.0.0.1:8000/api';

// ─── State ───────────────────────────────────────────────────────────────────
const guildInfo  = ref({ name: 'Memuat...', level: 0, exp: 0, gold: 0 });
const questsList = ref([]);
const loading    = ref(true);
const toast      = ref(null);

// Modal state
const showModal    = ref(false);
const isEditing    = ref(false);
const editingQuest = ref(null);
const form = ref({
  title: '', description: '', rank: 'C',
  reward_exp: 50, reward_gold: 100, doomsday: ''
});

// Drag state
const draggingId = ref(null);

// ─── Columns ─────────────────────────────────────────────────────────────────
const columns = [
  { key: 'available',  label: 'Available',  icon: '📜', color: '#6366f1' },
  { key: 'accepted',   label: 'Accepted',   icon: '⚔️',  color: '#f59e0b' },
  { key: 'in_battle',  label: 'In Battle',  icon: '🔥', color: '#ef4444' },
  { key: 'cleared',    label: 'Cleared',    icon: '✅', color: '#22c55e' },
];

const questsByStatus = (status) =>
  questsList.value.filter(q => q.status === status);

// ─── Rank config ─────────────────────────────────────────────────────────────
const rankConfig = {
  S: { color: '#fbbf24', bg: 'rgba(251,191,36,0.15)', label: 'RANK S' },
  A: { color: '#f87171', bg: 'rgba(248,113,113,0.15)', label: 'RANK A' },
  B: { color: '#60a5fa', bg: 'rgba(96,165,250,0.15)', label: 'RANK B' },
  C: { color: '#a3a3a3', bg: 'rgba(163,163,163,0.15)', label: 'RANK C' },
};

// EXP bar
const expPercent = computed(() => {
  const needed = (guildInfo.value.level || 1) * 1000;
  return Math.min(100, Math.round(((guildInfo.value.exp || 0) / needed) * 100));
});

// ─── API calls ────────────────────────────────────────────────────────────────
const fetchBoard = async () => {
  try {
    const res = await axios.get(`${API}/board`);
    guildInfo.value  = res.data.guild;
    questsList.value = res.data.quests;
  } catch (e) {
    showToast('❌ Gagal terhubung ke server!', 'error');
  } finally {
    loading.value = false;
  }
};

const refreshGuild = async () => {
  const res = await axios.get(`${API}/guild`);
  guildInfo.value = res.data;
};

const createQuest = async () => {
  try {
    const res = await axios.post(`${API}/quests`, form.value);
    questsList.value.push(res.data);
    showToast('📜 Quest baru telah diposting!');
    closeModal();
  } catch (e) {
    showToast('❌ Gagal membuat quest.', 'error');
  }
};

const updateQuest = async () => {
  try {
    const res = await axios.patch(`${API}/quests/${editingQuest.value.id}`, form.value);
    const idx = questsList.value.findIndex(q => q.id === editingQuest.value.id);
    if (idx !== -1) questsList.value[idx] = res.data;
    showToast('✏️ Quest berhasil diperbarui!');
    closeModal();
  } catch (e) {
    showToast('❌ Gagal memperbarui quest.', 'error');
  }
};

const deleteQuest = async (id) => {
  if (!confirm('Hapus quest ini?')) return;
  try {
    await axios.delete(`${API}/quests/${id}`);
    questsList.value = questsList.value.filter(q => q.id !== id);
    showToast('🗑️ Quest dihapus.');
  } catch (e) {
    showToast('❌ Gagal menghapus quest.', 'error');
  }
};

const moveQuest = async (questOrId, newStatus) => {
  // Selalu ambil dari questsList agar reaktif
  const id = typeof questOrId === 'object' ? questOrId.id : questOrId;
  const idx = questsList.value.findIndex(q => q.id === id);
  if (idx === -1) return;

  const oldStatus = questsList.value[idx].status;
  if (oldStatus === newStatus) return;

  const rewardExp  = questsList.value[idx].reward_exp;
  const rewardGold = questsList.value[idx].reward_gold;

  // Optimistic update langsung di array reaktif
  questsList.value[idx] = { ...questsList.value[idx], status: newStatus };

  try {
    const res = await axios.patch(`${API}/quests/${id}`, { status: newStatus });
    questsList.value[idx] = res.data;
    if (newStatus === 'cleared') {
      await refreshGuild();
      showToast(`⭐ Quest cleared! +${rewardExp} EXP, +${rewardGold} Gold`);
    } else {
      showToast(`🔄 Quest dipindah ke ${newStatus.replace('_', ' ')}.`);
    }
  } catch (e) {
    // Rollback
    questsList.value[idx] = { ...questsList.value[idx], status: oldStatus };
    showToast('❌ Gagal memindah quest.', 'error');
  }
};

// ─── Modal ───────────────────────────────────────────────────────────────────
const openCreate = () => {
  isEditing.value = false;
  form.value = { title: '', description: '', rank: 'C', reward_exp: 50, reward_gold: 100, doomsday: '' };
  showModal.value = true;
};

const openEdit = (quest) => {
  isEditing.value    = true;
  editingQuest.value = quest;
  form.value = {
    title: quest.title, description: quest.description || '',
    rank: quest.rank, reward_exp: quest.reward_exp,
    reward_gold: quest.reward_gold, doomsday: quest.doomsday || ''
  };
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submitForm = () => isEditing.value ? updateQuest() : createQuest();

// ─── Drag & Drop ─────────────────────────────────────────────────────────────
const onDragStart = (e, quest) => {
  draggingId.value = quest.id;
  e.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
};

const onDrop = (e, status) => {
  e.preventDefault();
  const quest = questsList.value.find(q => q.id === draggingId.value);
  if (quest) moveQuest(quest, status);
  draggingId.value = null;
};

// ─── Toast ───────────────────────────────────────────────────────────────────
let toastTimer = null;
const showToast = (msg, type = 'success') => {
  toast.value = { msg, type };
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = null; }, 3000);
};

onMounted(fetchBoard);
</script>

<template>
  <div class="app">

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" :class="['toast', toast.type]">{{ toast.msg }}</div>
    </Transition>

    <!-- ── Header / Guild Card ────────────────────────────── -->
    <header class="guild-header">
      <div class="guild-emblem">⚜</div>
      <div class="guild-info">
        <h1 class="guild-name">{{ guildInfo.name }}</h1>
        <div class="guild-stats">
          <span class="stat-badge lv">LV {{ guildInfo.level }}</span>
          <div class="exp-bar-wrap">
            <div class="exp-bar-label">EXP</div>
            <div class="exp-bar-track">
              <div class="exp-bar-fill" :style="{ width: expPercent + '%' }"></div>
            </div>
            <div class="exp-bar-num">{{ guildInfo.exp }}</div>
          </div>
        </div>
      </div>
      <div class="gold-box">
        <span class="gold-icon">💰</span>
        <span class="gold-val">{{ (guildInfo.gold || 0).toLocaleString() }}</span>
        <span class="gold-unit">Gold</span>
      </div>
      <button class="btn-post-quest" @click="openCreate">+ Post Quest</button>
    </header>

    <!-- ── Loading ───────────────────────────────────────── -->
    <div v-if="loading" class="loading-screen">
      <div class="spinner"></div>
      <p>Menghubungi Guild Master...</p>
    </div>

    <!-- ── Kanban Board ──────────────────────────────────── -->
    <main v-else class="kanban-board">
      <div
        v-for="col in columns"
        :key="col.key"
        class="kanban-col"
        :data-status="col.key"
        @dragover="onDragOver"
        @drop="onDrop($event, col.key)"
      >
        <!-- Column Header -->
        <div class="col-header" :style="{ borderColor: col.color }">
          <span class="col-icon">{{ col.icon }}</span>
          <span class="col-label">{{ col.label }}</span>
          <span class="col-count" :style="{ background: col.color }">
            {{ questsByStatus(col.key).length }}
          </span>
        </div>

        <!-- Drop Zone -->
        <div class="col-body">
          <div
            v-if="questsByStatus(col.key).length === 0"
            class="col-empty"
            :style="{ borderColor: col.color + '44' }"
          >
            <span>Drag quest ke sini</span>
          </div>

          <!-- Quest Cards -->
          <div
            v-for="quest in questsByStatus(col.key)"
            :key="quest.id"
            class="quest-card"
            :class="{ dragging: draggingId === quest.id }"
            draggable="true"
            @dragstart="onDragStart($event, quest)"
            @dragend="draggingId = null"
          >
            <!-- Rank Badge -->
            <div
              class="rank-badge"
              :style="{ color: rankConfig[quest.rank].color, background: rankConfig[quest.rank].bg }"
            >
              {{ rankConfig[quest.rank].label }}
            </div>

            <!-- Title -->
            <h3 class="quest-title">{{ quest.title }}</h3>

            <!-- Description -->
            <p v-if="quest.description" class="quest-desc">{{ quest.description }}</p>

            <!-- Doomsday -->
            <div v-if="quest.doomsday" class="quest-doomsday">
              ⏳ {{ quest.doomsday }}
            </div>

            <!-- Rewards -->
            <div class="quest-rewards">
              <span class="reward exp">⭐ {{ quest.reward_exp }} EXP</span>
              <span class="reward gold">💰 {{ quest.reward_gold }} G</span>
            </div>

            <!-- Actions -->
            <div class="quest-actions">
              <div class="move-btns">
                <template v-for="c in columns" :key="c.key">
                  <button
                    v-if="c.key !== quest.status"
                    class="btn-move"
                    :style="{ background: c.color + '22', color: c.color, borderColor: c.color + '55' }"
                    @click="moveQuest(quest, c.key)"
                    :title="'→ ' + c.label"
                  >{{ c.icon }}</button>
                </template>
              </div>
              <div class="edit-del-btns">
                <button class="btn-edit" @click="openEdit(quest)" title="Edit">✏️</button>
                <button class="btn-del"  @click="deleteQuest(quest.id)" title="Hapus">🗑️</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- ── Modal Tambah/Edit Quest ───────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">
          <div class="modal-header">
            <h2>{{ isEditing ? '✏️ Edit Quest' : '📜 Post Quest Baru' }}</h2>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>

          <div class="modal-body">
            <label>
              <span>Judul Quest <em>*</em></span>
              <input v-model="form.title" type="text" placeholder="Nama misi..." />
            </label>

            <label>
              <span>Deskripsi</span>
              <textarea v-model="form.description" rows="3" placeholder="Detail misi..."></textarea>
            </label>

            <div class="form-row">
              <label>
                <span>Rank <em>*</em></span>
                <select v-model="form.rank">
                  <option value="S">S — Legendary</option>
                  <option value="A">A — Epic</option>
                  <option value="B">B — Rare</option>
                  <option value="C">C — Common</option>
                </select>
              </label>
              <label>
                <span>Deadline</span>
                <input v-model="form.doomsday" type="text" placeholder="e.g. 3 Days Left" />
              </label>
            </div>

            <div class="form-row">
              <label>
                <span>Reward EXP</span>
                <input v-model.number="form.reward_exp" type="number" min="0" />
              </label>
              <label>
                <span>Reward Gold</span>
                <input v-model.number="form.reward_gold" type="number" min="0" />
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Batal</button>
            <button class="btn-submit" @click="submitForm">
              {{ isEditing ? 'Simpan Perubahan' : 'Post Quest' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;900&family=Crimson+Pro:ital,wght@0,300;0,400;0,600;1,400&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #0d0f17;
  --surface:   #141622;
  --surface2:  #1c1f30;
  --border:    rgba(255,255,255,0.07);
  --text:      #e2e0d8;
  --muted:     #6b6a7a;
  --gold:      #f0b429;
  --gold-dim:  rgba(240,180,41,0.15);
  --purple:    #7c6af7;
  --radius:    12px;
  --shadow:    0 8px 32px rgba(0,0,0,0.5);
}

body { background: var(--bg); color: var(--text); font-family: 'Crimson Pro', serif; }

.app {
  min-height: 100vh;
  background: var(--bg);
  background-image:
    radial-gradient(ellipse at 20% 0%, rgba(124,106,247,0.08) 0%, transparent 60%),
    radial-gradient(ellipse at 80% 100%, rgba(240,180,41,0.06) 0%, transparent 60%);
}

/* ── Toast ─────────────────────────────────────────────── */
.toast {
  position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%);
  background: var(--surface2); border: 1px solid var(--border);
  color: var(--text); padding: .7rem 1.5rem; border-radius: 50px;
  font-family: 'Crimson Pro', serif; font-size: 1rem;
  box-shadow: var(--shadow); z-index: 9999; white-space: nowrap;
}
.toast.error { border-color: rgba(239,68,68,0.5); color: #f87171; }
.toast-enter-active, .toast-leave-active { transition: all .3s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-12px); }

/* ── Header ─────────────────────────────────────────────── */
.guild-header {
  display: flex; align-items: center; gap: 1.5rem;
  padding: 1.5rem 2.5rem;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 4px 24px rgba(0,0,0,0.4);
}

.guild-emblem {
  font-size: 2.5rem; color: var(--gold);
  text-shadow: 0 0 20px rgba(240,180,41,0.5);
  flex-shrink: 0;
}

.guild-info { flex: 1; min-width: 0; }
.guild-name {
  font-family: 'Cinzel', serif; font-size: 1.5rem; font-weight: 900;
  color: var(--gold); letter-spacing: 0.1em;
  text-shadow: 0 0 30px rgba(240,180,41,0.3);
}

.guild-stats { display: flex; align-items: center; gap: 1rem; margin-top: .4rem; }
.stat-badge {
  font-family: 'Cinzel', serif; font-size: .75rem; font-weight: 600;
  padding: .2rem .7rem; border-radius: 50px;
  background: var(--gold-dim); color: var(--gold);
  border: 1px solid rgba(240,180,41,0.3);
}

.exp-bar-wrap { display: flex; align-items: center; gap: .5rem; }
.exp-bar-label { font-size: .75rem; color: var(--muted); font-family: 'Cinzel', serif; }
.exp-bar-track {
  width: 120px; height: 6px; background: var(--surface2);
  border-radius: 99px; overflow: hidden;
}
.exp-bar-fill {
  height: 100%; background: linear-gradient(90deg, var(--purple), #a78bfa);
  border-radius: 99px; transition: width .6s ease;
}
.exp-bar-num { font-size: .75rem; color: var(--muted); }

.gold-box {
  display: flex; align-items: center; gap: .4rem;
  background: var(--gold-dim); border: 1px solid rgba(240,180,41,0.25);
  padding: .6rem 1.2rem; border-radius: var(--radius);
}
.gold-icon { font-size: 1.2rem; }
.gold-val { font-family: 'Cinzel', serif; font-size: 1.2rem; color: var(--gold); font-weight: 700; }
.gold-unit { font-size: .8rem; color: rgba(240,180,41,0.6); }

.btn-post-quest {
  font-family: 'Cinzel', serif; font-size: .85rem; font-weight: 700;
  padding: .65rem 1.4rem; border-radius: var(--radius);
  background: linear-gradient(135deg, #7c6af7, #6050d0);
  color: #fff; border: none; cursor: pointer;
  box-shadow: 0 4px 16px rgba(124,106,247,0.35);
  transition: all .2s; white-space: nowrap;
}
.btn-post-quest:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(124,106,247,0.5); }

/* ── Loading ─────────────────────────────────────────────── */
.loading-screen {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 60vh; gap: 1rem; color: var(--muted);
}
.spinner {
  width: 40px; height: 40px; border: 3px solid var(--border);
  border-top-color: var(--purple); border-radius: 50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Kanban Board ────────────────────────────────────────── */
.kanban-board {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
  padding: 2rem 2.5rem;
  min-height: calc(100vh - 90px);
}

@media (max-width: 1100px) { .kanban-board { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .kanban-board { grid-template-columns: 1fr; } }

.kanban-col {
  display: flex; flex-direction: column;
  background: var(--surface);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  overflow: hidden;
  transition: box-shadow .2s;
}
.kanban-col:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.kanban-col[dragover] { box-shadow: 0 0 0 2px var(--purple); }

.col-header {
  display: flex; align-items: center; gap: .6rem;
  padding: 1rem 1.2rem;
  border-bottom: 2px solid;
  background: var(--surface2);
}
.col-icon { font-size: 1.1rem; }
.col-label {
  flex: 1; font-family: 'Cinzel', serif; font-size: .85rem;
  font-weight: 600; letter-spacing: 0.08em; color: var(--text);
}
.col-count {
  font-family: 'Cinzel', serif; font-size: .75rem; font-weight: 700;
  padding: .15rem .55rem; border-radius: 50px; color: #fff;
}

.col-body { flex: 1; padding: .75rem; display: flex; flex-direction: column; gap: .75rem; min-height: 120px; }

.col-empty {
  flex: 1; display: flex; align-items: center; justify-content: center;
  border: 2px dashed; border-radius: var(--radius);
  color: var(--muted); font-size: .85rem; font-style: italic;
  padding: 2rem; text-align: center; min-height: 80px;
}

/* ── Quest Card ─────────────────────────────────────────── */
.quest-card {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem;
  cursor: grab;
  transition: transform .2s, box-shadow .2s, opacity .2s;
  user-select: none;
}
.quest-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.4); }
.quest-card.dragging { opacity: .4; transform: scale(.97); cursor: grabbing; }
.quest-card:active { cursor: grabbing; }

.rank-badge {
  display: inline-block; font-family: 'Cinzel', serif;
  font-size: .65rem; font-weight: 700; letter-spacing: .12em;
  padding: .2rem .6rem; border-radius: 4px; margin-bottom: .6rem;
}

.quest-title {
  font-family: 'Cinzel', serif; font-size: .95rem; font-weight: 600;
  color: var(--text); line-height: 1.3; margin-bottom: .4rem;
}

.quest-desc {
  font-size: .85rem; color: var(--muted); line-height: 1.5;
  margin-bottom: .5rem;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden;
}

.quest-doomsday {
  font-size: .8rem; color: #f87171;
  margin-bottom: .5rem; font-style: italic;
}

.quest-rewards {
  display: flex; gap: .5rem; margin-bottom: .75rem; flex-wrap: wrap;
}
.reward {
  font-size: .75rem; padding: .2rem .6rem; border-radius: 50px;
  font-family: 'Cinzel', serif; font-weight: 600;
}
.reward.exp  { background: rgba(167,139,250,0.15); color: #c4b5fd; }
.reward.gold { background: var(--gold-dim); color: var(--gold); }

.quest-actions {
  display: flex; align-items: center;
  justify-content: space-between;
  gap: .4rem;
  flex-wrap: wrap;
  overflow: hidden;
}
.move-btns {
  display: flex; gap: .25rem;
  flex-wrap: wrap; flex: 1; min-width: 0;
}
.edit-del-btns { display: flex; gap: .25rem; flex-shrink: 0; }

.btn-move {
  font-size: .8rem; padding: .2rem .4rem; border-radius: 6px;
  border: 1px solid; cursor: pointer; transition: all .15s;
  background: transparent; line-height: 1;
  flex-shrink: 0;
}
.btn-move:hover { transform: scale(1.1); }

.btn-edit, .btn-del {
  font-size: .8rem; padding: .2rem .4rem; border-radius: 6px;
  border: 1px solid var(--border); cursor: pointer;
  background: var(--surface); transition: all .15s; line-height: 1;
}
.btn-edit:hover { border-color: #7c6af7; background: rgba(124,106,247,0.15); }
.btn-del:hover  { border-color: #ef4444; background: rgba(239,68,68,0.15); }

/* ── Modal ──────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.7);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000; padding: 1rem;
  backdrop-filter: blur(4px);
}

.modal-box {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 16px; width: 100%; max-width: 520px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.6);
  overflow: hidden;
}

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
}
.modal-header h2 { font-family: 'Cinzel', serif; font-size: 1.1rem; color: var(--gold); }
.modal-close {
  background: none; border: none; color: var(--muted); cursor: pointer;
  font-size: 1.1rem; padding: .2rem .5rem; border-radius: 6px;
  transition: color .2s;
}
.modal-close:hover { color: var(--text); }

.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }

.modal-body label { display: flex; flex-direction: column; gap: .4rem; }
.modal-body label span { font-size: .8rem; color: var(--muted); font-family: 'Cinzel', serif; letter-spacing: .05em; }
.modal-body label em { color: #ef4444; font-style: normal; }

.modal-body input, .modal-body textarea, .modal-body select {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 8px; padding: .65rem .9rem; color: var(--text);
  font-family: 'Crimson Pro', serif; font-size: 1rem;
  transition: border-color .2s; outline: none; resize: none; width: 100%;
}
.modal-body input:focus, .modal-body textarea:focus, .modal-body select:focus {
  border-color: var(--purple);
}
.modal-body select option { background: var(--surface2); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.modal-footer {
  display: flex; justify-content: flex-end; gap: .75rem;
  padding: 1rem 1.5rem; border-top: 1px solid var(--border);
  background: var(--surface2);
}

.btn-cancel {
  font-family: 'Cinzel', serif; font-size: .85rem; padding: .6rem 1.2rem;
  border-radius: 8px; border: 1px solid var(--border);
  background: transparent; color: var(--muted); cursor: pointer; transition: all .2s;
}
.btn-cancel:hover { border-color: var(--text); color: var(--text); }

.btn-submit {
  font-family: 'Cinzel', serif; font-size: .85rem; padding: .6rem 1.4rem;
  border-radius: 8px; border: none;
  background: linear-gradient(135deg, #7c6af7, #6050d0);
  color: #fff; cursor: pointer; transition: all .2s;
  box-shadow: 0 4px 12px rgba(124,106,247,0.3);
}
.btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(124,106,247,0.5); }

/* ── Modal Transition ────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: all .25s; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(.95); }
</style>