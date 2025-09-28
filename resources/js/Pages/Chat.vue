<script setup>
import { ref, watch } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3' // fusion des imports
import axios from 'axios'

const logout = () => router.post(route('logout'))

const page = usePage()
const models = page.props.models || []
const initialConversations = page.props.conversations || []

const currentModel = ref(models[0]?.id || '')
const userInput = ref('')

const conversationId = ref(null)
const currentTitle = ref('New chat')
const conversations = ref(initialConversations) // [{id,title,updated_at}]
const messages = ref([]) // [{role, content}]
const loading = ref(false)

//state pour le menu "⋯" par item
const openMenuId = ref(null)
const toggleMenuFor = (id) => {
    openMenuId.value = openMenuId.value === id ? null : id
}

watch(currentModel, () => {
    conversationId.value = null
    currentTitle.value = 'New chat'
    messages.value = []
})

function sendDisabledReason() {
    if (!currentModel.value) return 'Choose a model'
    if (!userInput.value.trim()) return 'Type a message'
    return null
}

async function loadConversation(id) {
    try {
        messages.value = []
        const {data} = await axios.get(`/chat/${id}`)
        conversationId.value = data.id
        currentTitle.value = data.title || 'Untitled'
        messages.value = data.messages || []
    } catch (e) {
        console.error(e)
    }
}

//Renommer une conversation
async function renameConversation(c) {
    const proposed = (c.title || '').trim()
    const input = window.prompt('New title (max 60 chars):', proposed)
    if (input == null) return // cancel
    const title = input.trim().slice(0, 60)
    if (!title) return

    try {
        const {data} = await axios.patch(`/chat/${c.id}/title`, {title})
        // mets à jour la liste (et remonte en tête)
        const idx = conversations.value.findIndex(x => x.id === c.id)
        if (idx > -1) {
            const item = conversations.value.splice(idx, 1)[0]
            item.title = data.title
            item.updated_at = new Date().toISOString()
            conversations.value.unshift(item)
        }
        // si c'est la conv active, mets à jour le titre courant
        if (conversationId.value === c.id) currentTitle.value = data.title
    } catch (e) {
        console.error(e)
        alert('Rename failed.')
    } finally {
        openMenuId.value = null
    }
}

//Supprimer une conversation
async function deleteConversation(c) {
    if (!window.confirm('Delete this conversation?')) return
    try {
        await axios.delete(`/chat/${c.id}`)
        const idx = conversations.value.findIndex(x => x.id === c.id)
        if (idx > -1) conversations.value.splice(idx, 1)

        // si on supprimait la conv en cours → reset
        if (conversationId.value === c.id) {
            conversationId.value = null
            currentTitle.value = 'New chat'
            messages.value = []
        }
    } catch (e) {
        console.error(e)
        alert('Delete failed.')
    } finally {
        openMenuId.value = null
    }
}

async function send() {
    if (sendDisabledReason()) return
    loading.value = true
    try {
        const content = userInput.value
        userInput.value = ''

        messages.value.push({role: 'user', content})

        const {data} = await axios.post('/chat/send', {
            model: currentModel.value,
            content,
            conversation_id: conversationId.value,
        })

        if (data?.ok) {
            if (!conversationId.value && data.conversation_id) {
                conversationId.value = data.conversation_id
                currentTitle.value = data.title || currentTitle.value
                conversations.value.unshift({
                    id: data.conversation_id,
                    title: data.title || 'Untitled',
                    updated_at: new Date().toISOString(),
                })
            } else {
                const idx = conversations.value.findIndex(c => c.id === conversationId.value)
                if (idx > -1) {
                    const item = conversations.value.splice(idx, 1)[0]
                    item.updated_at = new Date().toISOString()
                    if (data.title && item.title !== data.title) item.title = data.title
                    conversations.value.unshift(item)
                }
            }
            messages.value.push({role: 'assistant', content: data.text || '(empty)'})
        } else {
            messages.value.push({role: 'assistant', content: data?.error || 'API error.'})
        }
    } catch (e) {
        console.error(e)
        const apiError = e?.response?.data?.error
        messages.value.push({role: 'assistant', content: apiError || 'Network error.'})
    } finally {
        loading.value = false
    }
}

async function sendStream() {
    if (sendDisabledReason()) return
    loading.value = true
    try {
        const content = userInput.value
        userInput.value = ''
        messages.value.push({role: 'user', content})

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (!csrf) {
            messages.value.push({
                role: 'assistant',
                content: '[Erreur] CSRF token introuvable. Ajoute <meta name="csrf-token" content="{{ csrf_token() }}"> dans resources/views/app.blade.php',
            })
            return
        }

        const resp = await fetch('/chat/stream', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                model: currentModel.value,
                content,
                conversation_id: conversationId.value,
            }),
        })

        if (!resp.ok || !resp.body) {
            if (resp.status === 419) {
                messages.value.push({
                    role: 'assistant',
                    content: 'Stream failed to start (419 CSRF). Recharge la page.'
                })
            } else {
                messages.value.push({role: 'assistant', content: `Stream failed to start (HTTP ${resp.status}).`})
            }
            return
        }

        messages.value.push({role: 'assistant', content: ''})
        const assistantIndex = messages.value.length - 1
        const reader = resp.body.getReader()
        const decoder = new TextDecoder('utf-8')
        let buffer = ''

        while (true) {
            const {value, done} = await reader.read()
            if (done) break
            buffer += decoder.decode(value, {stream: true})

            const parts = buffer.split('\n\n')
            buffer = parts.pop() || ''

            for (const part of parts) {
                const lines = part.split('\n')
                const eventLine = lines.find(l => l.startsWith('event:'))
                const dataLine = lines.find(l => l.startsWith('data:'))
                const event = eventLine ? eventLine.replace('event:', '').trim() : 'message'
                const dataStr = dataLine ? dataLine.replace('data:', '').trim() : ''

                if (!dataStr) continue

                if (event === 'meta') {
                    const meta = JSON.parse(dataStr)
                    if (!conversationId.value && meta.conversation_id) {
                        conversationId.value = meta.conversation_id
                        currentTitle.value = meta.title || currentTitle.value
                        const idx = conversations.value.findIndex(c => c.id === meta.conversation_id)
                        if (idx === -1) {
                            conversations.value.unshift({
                                id: meta.conversation_id,
                                title: meta.title || 'Untitled',
                                updated_at: new Date().toISOString(),
                            })
                        }
                    }
                } else if (event === 'token') {
                    const tokenObj = JSON.parse(dataStr)
                    const delta = tokenObj.content || ''
                    messages.value[assistantIndex].content += delta
                } else if (event === 'error') {
                    const err = JSON.parse(dataStr)
                    messages.value[assistantIndex].content += `\n[Error] ${err.message || 'API error.'}`
                } else if (event === 'end') {
                    // fin du stream
                }
            }
        }
    } catch (e) {
        console.error(e)
        messages.value.push({role: 'assistant', content: 'Network error.'})
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="min-h-screen grid lg:grid-cols-[320px_1fr]">
        <!-- Sidebar: historique -->
        <aside class="border-r bg-gray-50 p-4 overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">Conversations</h2>
                <button
                    class="px-3 py-1 rounded bg-black text-white"
                    @click="() => { conversationId.value = null; currentTitle.value = 'New chat'; messages.value = [] }"
                >
                    New
                </button>
            </div>

            <ul class="space-y-1">
                <li v-for="c in conversations" :key="c.id" class="group relative">
                    <div class="flex items-center justify-between">
                        <a
                            class="block px-2 py-1 rounded hover:bg-gray-200 cursor-pointer flex-1 truncate"
                            @click.prevent="loadConversation(c.id)"
                            :title="c.title || 'Untitled'"
                        >
                            {{ c.title || 'Untitled' }}
                        </a>

                        <!-- bouton ⋯ visible au hover -->
                        <button
                            class="opacity-0 group-hover:opacity-100 transition inline-flex items-center px-2 py-1 rounded hover:bg-gray-200"
                            @click.stop="toggleMenuFor(c.id)"
                            aria-label="More"
                            title="More"
                        >
                            ⋯
                        </button>
                    </div>

                    <!-- menu contextuel -->
                    <div
                        v-if="openMenuId === c.id"
                        class="absolute right-0 z-10 mt-1 w-36 rounded-md border bg-white shadow"
                    >
                        <button
                            class="w-full text-left px-3 py-2 hover:bg-gray-50"
                            @click.stop="renameConversation(c)"
                        >
                            Rename
                        </button>
                        <button
                            class="w-full text-left px-3 py-2 text-red-600 hover:bg-red-50"
                            @click.stop="deleteConversation(c)"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
        </aside>

        <!-- Main -->
        <main class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold truncate">{{ currentTitle }}</h1>

                <!-- actions : Settings + Logout -->
                <div class="flex items-center gap-2">
                    <Link :href="route('settings.edit')"
                          class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">
                        ⚙ Settings
                    </Link>
                    <button @click="logout"
                            class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">
                        Logout
                    </button>
                </div>
            </div>

            <!-- Sélecteur de modèles -->
            <div class="bg-white p-4 rounded-2xl shadow">
                <label class="block text-sm font-medium mb-2">Model</label>
                <select v-model="currentModel" class="border rounded px-3 py-2 w-full">
                    <option v-for="m in models" :key="m.id" :value="m.id">
                        {{ m.label }} — {{ m.id }}
                    </option>
                </select>
            </div>

            <!-- Messages -->
            <div class="bg-white p-4 rounded-2xl shadow min-h-[280px] space-y-3">
                <div v-for="(m,i) in messages" :key="i" class="whitespace-pre-wrap">
                    <div class="text-xs text-gray-500 mb-1">{{ m.role }}</div>
                    <div>{{ m.content }}</div>
                </div>
            </div>

            <!-- Saisie + Envoi -->
            <div class="bg-white p-4 rounded-2xl shadow">
                <label class="block text-sm font-medium mb-2">Your message</label>
                <div class="flex gap-2">
                    <input
                        v-model="userInput"
                        class="border rounded px-3 py-2 flex-1"
                        placeholder="Ask something…"
                        @keyup.enter="send"
                    />
                    <button
                        class="px-4 py-2 rounded-xl bg-black text-white disabled:opacity-50"
                        :disabled="loading || !!(!currentModel || !userInput.trim())"
                        @click.prevent="send"
                    >
                        {{ loading ? 'Sending…' : 'Send' }}
                    </button>
                    <button
                        class="px-4 py-2 rounded-xl bg-black text-white disabled:opacity-50"
                        :disabled="loading || !!(!currentModel || !userInput.trim())"
                        @click.prevent="sendStream"
                    >
                        {{ loading ? 'Streaming…' : 'Stream' }}
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
