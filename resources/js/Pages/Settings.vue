<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, Link, router } from '@inertiajs/vue3' //Link, router

const page = usePage()
const initial = page.props.preferences || {
    tone: 'neutral',
    style: 'concise',
    context: '',
    custom_system: ''
}

// useForm pour gérer POST + erreurs
const form = useForm({
    tone: initial.tone,
    style: initial.style,
    context: initial.context,
    custom_system: initial.custom_system,
})

const hasCustom = computed(() => !!form.custom_system?.trim())

function submit() {
    form.post(route('settings.update'))
}

// ADDED: logout helper
const logout = () => {
    router.post(route('logout'))
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <h1 class="text-2xl font-bold">AI Settings (Instructions personnalisées)</h1>

            <!--ADDED: action bar -->
            <div class="flex items-center justify-end gap-2">
                <Link :href="route('chat.index')"
                      class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">
                    ← Back to Chat
                </Link>
                <button @click="logout"
                        class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">
                    Logout
                </button>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow space-y-4">
                <p class="text-sm text-gray-600">
                    Configure le comportement de l’IA. Si <strong>Custom system</strong> est rempli, il
                    <em>prime</em> sur les champs Ton/Style/Contexte.
                </p>

                <!-- Custom system prompt (prioritaire) -->
                <div>
                    <label class="block text-sm font-medium mb-1">Custom system (prioritaire)</label>
                    <textarea
                        v-model="form.custom_system"
                        class="w-full border rounded-lg px-3 py-2 min-h-[120px]"
                        placeholder="(Optionnel) Écris ici un prompt système complet et strict…"
                    />
                    <div v-if="form.errors.custom_system" class="text-sm text-red-600 mt-1">
                        {{ form.errors.custom_system }}
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4 opacity-100" :class="{'opacity-50 pointer-events-none': hasCustom}">
                    <!-- Tone -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Tone</label>
                        <select v-model="form.tone" class="w-full border rounded-lg px-3 py-2">
                            <option value="neutral">Neutral</option>
                            <option value="friendly">Friendly</option>
                            <option value="professional">Professional</option>
                            <option value="enthusiastic">Enthusiastic</option>
                            <option value="strict">Strict</option>
                        </select>
                        <div v-if="form.errors.tone" class="text-sm text-red-600 mt-1">
                            {{ form.errors.tone }}
                        </div>
                    </div>

                    <!-- Style -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Style</label>
                        <select v-model="form.style" class="w-full border rounded-lg px-3 py-2">
                            <option value="concise">Concise</option>
                            <option value="detailed">Detailed</option>
                            <option value="creative">Creative</option>
                            <option value="step-by-step">Step-by-step</option>
                            <option value="bullet-list">Bullet-list</option>
                        </select>
                        <div v-if="form.errors.style" class="text-sm text-red-600 mt-1">
                            {{ form.errors.style }}
                        </div>
                    </div>
                </div>

                <!-- Context -->
                <div :class="{'opacity-50 pointer-events-none': hasCustom}">
                    <label class="block text-sm font-medium mb-1">Context</label>
                    <textarea
                        v-model="form.context"
                        class="w-full border rounded-lg px-3 py-2 min-h-[100px]"
                        placeholder="Ex: Tu aides un étudiant à préparer un examen Laravel. Réponses adaptées au niveau BAC+2…"
                    />
                    <div v-if="form.errors.context" class="text-sm text-red-600 mt-1">
                        {{ form.errors.context }}
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        class="px-5 py-2 rounded-xl bg-black text-white disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : 'Save settings' }}
                    </button>
                </div>

                <div v-if="$page.props.flash?.success" class="text-sm text-green-700">
                    {{ $page.props.flash.success }}
                </div>
            </div>

            <div class="text-sm text-gray-500">
                Effet : au prochain message, le prompt système injecté sera construit avec ces réglages.
            </div>
        </div>
    </div>
</template>
