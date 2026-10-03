<template>
  <div class="rich-editor rounded-xl border a-border-2 a-panel">
    <!-- Toolbar and its option bars stay at the top while long content scrolls -->
    <div class="sticky top-0 z-20 rounded-t-xl">
    <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b a-border a-panel-2 rounded-t-xl">
      <!-- Text style: shows the style of the line the cursor is on; each option is drawn at its real size -->
      <div class="relative" ref="styleMenu">
        <button type="button" @click="styleOpen = !styleOpen" :disabled="source" class="h-8 min-w-[8.5rem] inline-flex items-center justify-between gap-2 rounded-md px-2.5 text-xs font-semibold border a-border a-panel hover:border-[var(--a-border-2)] disabled:opacity-50" :aria-expanded="styleOpen" title="Text style">
          <span>{{ blockLabel }}</span>
          <svg class="w-3.5 h-3.5 a-subtle" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" /></svg>
        </button>
        <div v-if="styleOpen" class="absolute left-0 top-full mt-1 z-30 w-64 rounded-xl border a-border a-panel p-1.5 shadow-xl">
          <button v-for="o in blockOptions" :key="o.value" type="button" @mousedown.prevent="setBlock(o.value)"
            :class="['w-full text-left rounded-lg px-3 py-1.5 flex items-center justify-between gap-3 hover:bg-[var(--a-panel-3)]', blockType === o.value && 'bg-[var(--a-accent-soft)]']">
            <span :class="o.cls">{{ o.label }}</span>
            <span class="text-[10px] font-mono a-subtle shrink-0">{{ o.tag }}</span>
          </button>
          <p class="px-3 pt-1.5 pb-1 text-[10.5px] a-subtle border-t a-border mt-1">H1 is the page title. Use H2 for sections, H3–H6 for points inside them.</p>
        </div>
      </div>
      <span class="sep"></span>
      <button type="button" v-for="b in markButtons" :key="b.name" @click="b.run" :class="btn(isActive(b.name))" :title="b.title" :disabled="source" v-html="b.icon"></button>
      <span class="sep"></span>
      <button type="button" v-for="b in blockButtons" :key="b.name" @click="b.run" :class="btn(isActive(b.name))" :title="b.title" :disabled="source" v-html="b.icon"></button>
      <span class="sep"></span>
      <button type="button" v-for="a in alignButtons" :key="a.value" @click="setTextAlign(a.value)" :class="btn(textAlign() === a.value)" :title="a.title" :disabled="source || imageSelected()" v-html="a.icon"></button>
      <span class="sep"></span>
      <button type="button" @click="toggleLinkBar" :class="btn(isActive('link'))" title="Link" :disabled="source" v-html="icons.link"></button>
      <button type="button" @click="pickerOpen = true" :class="btn(false)" title="Insert image" :disabled="source" v-html="icons.image"></button>
      <button type="button" @click="insertTable" :class="btn(isActive('table'))" title="Insert table" :disabled="source" v-html="icons.table"></button>
      <span class="sep"></span>
      <button type="button" @click="editor?.chain().focus().undo().run()" :class="btn(false)" title="Undo" :disabled="source" v-html="icons.undo"></button>
      <button type="button" @click="editor?.chain().focus().redo().run()" :class="btn(false)" title="Redo" :disabled="source" v-html="icons.redo"></button>
      <button type="button" @click="toggleSource" :class="[btn(source), 'ml-auto text-[11px] font-mono px-2 w-auto']" title="Edit HTML">&lt;/&gt;</button>
    </div>

    <div v-if="linkBar && !source" class="flex flex-wrap items-center gap-2 px-3 py-2 border-b a-border a-tint-warning">
      <input ref="linkInput" v-model="linkUrl" @keydown.enter.prevent="applyLink" type="text" placeholder="/service/water-heater-repair or https://…" class="admin-input text-xs py-1.5 flex-1 min-w-[14rem]" />
      <label class="flex items-center gap-1.5 text-xs a-muted"><input v-model="linkNewTab" type="checkbox" class="rounded a-border-2" /> New tab</label>
      <button type="button" @click="applyLink" class="admin-btn-primary text-xs py-1.5 px-3">Apply</button>
      <button v-if="isActive('link')" type="button" @click="removeLink" class="text-xs font-semibold a-text-danger">Remove</button>
      <span class="w-full text-[11px] a-muted">Internal links (starting with /) are best for SEO: link articles to their service page.</span>
    </div>

    <div v-if="isActive('table') && !source" class="flex flex-wrap items-center gap-1 px-3 py-1.5 border-b a-border a-panel-2 text-xs">
      <span class="font-semibold a-muted mr-1">Table:</span>
      <button type="button" class="tbl" @click="editor.chain().focus().addRowAfter().run()">+ Row</button>
      <button type="button" class="tbl" @click="editor.chain().focus().addColumnAfter().run()">+ Column</button>
      <button type="button" class="tbl" @click="editor.chain().focus().deleteRow().run()">− Row</button>
      <button type="button" class="tbl" @click="editor.chain().focus().deleteColumn().run()">− Column</button>
      <button type="button" class="tbl" @click="editor.chain().focus().toggleHeaderRow().run()">Header row</button>
      <button type="button" class="tbl a-text-danger" @click="editor.chain().focus().deleteTable().run()">Delete table</button>
    </div>

    <!-- Selected image: size and alt text -->
    <div v-if="imageSelected() && !source" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2 border-b a-border a-panel-2 text-xs">
      <span class="font-semibold a-muted">Position:</span>
      <div class="flex items-center gap-1">
        <button v-for="p in imagePositions" :key="p.value" type="button" :class="['tbl', imageAlign() === p.value && 'a-inverse']" :title="p.title" @click="setImageAlign(p.value)">{{ p.label }}</button>
      </div>
      <span class="font-semibold a-muted">Size:</span>
      <div class="flex items-center gap-1">
        <button v-for="s in imageSizes" :key="s.label" type="button" :class="['tbl', imageSize() === s.pct && 'a-inverse']" @click="setImageSize(s.pct)">{{ s.label }}</button>
      </div>
      <label class="flex items-center gap-2 flex-1 min-w-[16rem]">
        <span class="font-semibold a-muted shrink-0">Alt text</span>
        <input :value="imageAlt()" @input="setImageAlt($event.target.value)" @keydown.enter.prevent type="text" maxlength="160" placeholder="Describe the photo, e.g. Leaking pipe under an HDB kitchen sink" class="admin-input text-xs py-1.5 flex-1" />
      </label>
      <button type="button" class="tbl a-text-danger" @click="editor.chain().focus().deleteSelection().run()">Remove</button>
      <span class="w-full text-[11px] a-muted">Left or Right puts the photo beside the text that follows it. Drag a corner to resize freely. Alt text tells Google and screen readers what the photo shows.</span>
    </div>
    </div>

    <EditorContent v-show="!source" :editor="editor" class="rich-editor-content a-text" :style="{ minHeight }" @click="onTextClick" />
    <textarea v-if="source" v-model="sourceHtml" @input="emit('update:modelValue', sourceHtml)" class="w-full font-mono text-xs p-4 a-panel-2 a-muted outline-none" :style="{ minHeight }"></textarea>

    <MediaPicker :show="pickerOpen" @close="pickerOpen = false" @insert="insertImage" />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import MediaPicker from '@/Components/Admin/MediaPicker.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Write here. Use Heading 2 for main sections, Heading 3 for sub-points.' },
  minHeight: { type: String, default: '280px' },
});
const emit = defineEmits(['update:modelValue']);

// The page H1 comes from the title, so any H1 inside legacy content becomes H2.
const prepare = html => (html || '').replace(/<h1(\s|>)/gi, '<h2$1').replace(/<\/h1>/gi, '</h2>');

const editor = useEditor({
  content: prepare(props.modelValue),
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3, 4, 5, 6] },
      codeBlock: false,
      code: false,
      link: { openOnClick: false, autolink: true, HTMLAttributes: { target: null, rel: null } },
    }),
    // data-align: left / right float the photo so the following text wraps beside it.
    Image.extend({
      addAttributes() {
        return {
          ...this.parent?.(),
          align: {
            default: null,
            parseHTML: (el) => (['left', 'center', 'right'].includes(el.getAttribute('data-align')) ? el.getAttribute('data-align') : null),
            renderHTML: (attrs) => (attrs.align ? { 'data-align': attrs.align } : {}),
          },
        };
      },
    }).configure({
      allowBase64: false,
      HTMLAttributes: { loading: 'lazy' },
      resize: { enabled: true, directions: ['top-left', 'top-right', 'bottom-left', 'bottom-right'], minWidth: 80, minHeight: 60, alwaysPreserveAspectRatio: true },
    }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
    Placeholder.configure({ placeholder: props.placeholder }),
    TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML());
  },
});

watch(() => props.modelValue, (value) => {
  if (!editor.value || source.value) return;
  const current = editor.value.isEmpty ? '' : editor.value.getHTML();
  if ((value || '') !== current) {
    editor.value.commands.setContent(prepare(value), { emitUpdate: false });
  }
});

onBeforeUnmount(() => editor.value?.destroy());

const isActive = (name, attrs) => !!editor.value?.isActive(name, attrs);

const blockOptions = [
  { value: 'p', label: 'Paragraph', tag: 'P', cls: 'text-sm' },
  { value: '2', label: 'Heading 2', tag: 'H2', cls: 'text-[1.2rem] font-bold' },
  { value: '3', label: 'Heading 3', tag: 'H3', cls: 'text-[1.05rem] font-bold' },
  { value: '4', label: 'Heading 4', tag: 'H4', cls: 'text-[0.95rem] font-bold' },
  { value: '5', label: 'Heading 5', tag: 'H5', cls: 'text-[0.85rem] font-bold' },
  { value: '6', label: 'Heading 6', tag: 'H6', cls: 'text-[0.75rem] font-bold uppercase tracking-wider' },
];
const blockType = computed(() => {
  for (const level of [2, 3, 4, 5, 6]) if (isActive('heading', { level })) return String(level);
  return 'p';
});
const blockLabel = computed(() => blockOptions.find((o) => o.value === blockType.value)?.label || 'Paragraph');
const styleOpen = ref(false);
const styleMenu = ref(null);
function setBlock(value) {
  const chain = editor.value.chain().focus();
  value === 'p' ? chain.setParagraph().run() : chain.setHeading({ level: Number(value) }).run();
  styleOpen.value = false;
}
const closeStyle = (e) => { if (styleOpen.value && styleMenu.value && !styleMenu.value.contains(e.target)) styleOpen.value = false; };
document.addEventListener('mousedown', closeStyle);
onBeforeUnmount(() => document.removeEventListener('mousedown', closeStyle));

const icons = {
  bold: '<b>B</b>',
  italic: '<i class="font-serif">I</i>',
  underline: '<span class="underline">U</span>',
  strike: '<span class="line-through">S</span>',
  bullet: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/></svg>',
  ordered: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 6h10M10 12h10M10 18h10"/><text x="2" y="8" font-size="6" fill="currentColor" stroke="none">1</text><text x="2" y="14" font-size="6" fill="currentColor" stroke="none">2</text><text x="2" y="20" font-size="6" fill="currentColor" stroke="none">3</text></svg>',
  quote: '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M7 7h4v4H9c0 2 1 3 2 3v2c-3 0-4-2-4-5V7zm7 0h4v4h-2c0 2 1 3 2 3v2c-3 0-4-2-4-5V7z"/></svg>',
  hr: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 12h16"/></svg>',
  link: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>',
  image: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
  table: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 5h18v14H3zM3 10h18M3 15h18M9 5v14M15 5v14"/></svg>',
  undo: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14L4 9l5-5M4 9h11a5 5 0 010 10h-3"/></svg>',
  redo: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 14l5-5-5-5M20 9H9a5 5 0 000 10h3"/></svg>',
};

const markButtons = [
  { name: 'bold', title: 'Bold', icon: icons.bold, run: () => editor.value.chain().focus().toggleBold().run() },
  { name: 'italic', title: 'Italic', icon: icons.italic, run: () => editor.value.chain().focus().toggleItalic().run() },
  { name: 'underline', title: 'Underline', icon: icons.underline, run: () => editor.value.chain().focus().toggleUnderline().run() },
  { name: 'strike', title: 'Strikethrough', icon: icons.strike, run: () => editor.value.chain().focus().toggleStrike().run() },
];
const alignIcon = (lines) => `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="${lines}"/></svg>`;
const alignButtons = [
  { value: 'left', title: 'Align left', icon: alignIcon('M4 6h16M4 10h10M4 14h16M4 18h10') },
  { value: 'center', title: 'Align center', icon: alignIcon('M4 6h16M7 10h10M4 14h16M7 18h10') },
  { value: 'right', title: 'Align right', icon: alignIcon('M4 6h16M10 10h10M4 14h16M10 18h10') },
  { value: 'justify', title: 'Justify', icon: alignIcon('M4 6h16M4 10h16M4 14h16M4 18h16') },
];
const textAlign = () => ['center', 'right', 'justify'].find((a) => editor.value?.isActive({ textAlign: a })) || 'left';
function setTextAlign(value) {
  const chain = editor.value.chain().focus();
  value === 'left' ? chain.unsetTextAlign().run() : chain.setTextAlign(value).run();
}

const blockButtons = [
  { name: 'bulletList', title: 'Bullet list', icon: icons.bullet, run: () => editor.value.chain().focus().toggleBulletList().run() },
  { name: 'orderedList', title: 'Numbered list', icon: icons.ordered, run: () => editor.value.chain().focus().toggleOrderedList().run() },
  { name: 'blockquote', title: 'Quote', icon: icons.quote, run: () => editor.value.chain().focus().toggleBlockquote().run() },
  { name: 'horizontalRule', title: 'Divider', icon: icons.hr, run: () => editor.value.chain().focus().setHorizontalRule().run() },
];

function btn(active) {
  return [
    'w-8 h-8 inline-flex items-center justify-center rounded-md text-sm transition disabled:opacity-30',
    active ? 'a-inverse' : 'a-muted  hover:bg-[var(--a-panel-3)] ',
  ];
}

const linkBar = ref(false);
const linkUrl = ref('');
const linkNewTab = ref(false);
const linkInput = ref(null);

function openLinkBar(focus = true) {
  const attrs = editor.value.getAttributes('link');
  linkUrl.value = attrs.href || '';
  linkNewTab.value = attrs.target === '_blank';
  linkBar.value = true;
  if (focus) nextTick(() => linkInput.value?.focus());
}
// A click on a link in the text shows its link bar (with its address); a click anywhere else in the
// text closes it. Checked after the click has moved the cursor, so the link's details are read.
function onTextClick(event) {
  const a = event.target?.closest?.('a');
  if (!a) {
    linkBar.value = false;
    return;
  }
  // Read the address from the clicked link itself (the cursor may sit on its edge).
  linkUrl.value = a.getAttribute('href') || '';
  linkNewTab.value = a.getAttribute('target') === '_blank';
  linkBar.value = true;
  // Put the cursor inside the link, so Apply / Remove change this link.
  try {
    const pos = editor.value.view.posAtDOM(a, 0);
    editor.value.commands.setTextSelection(pos + 1);
  } catch (e) {
    // the cursor stays where the click put it
  }
}
function toggleLinkBar() {
  linkBar.value = !linkBar.value;
  if (linkBar.value) {
    openLinkBar();
  }
}
function applyLink() {
  const href = linkUrl.value.trim();
  if (!href) return removeLink();
  if (/^\s*javascript:/i.test(href)) return;
  const external = /^https?:\/\//i.test(href);
  editor.value.chain().focus().extendMarkRange('link').setLink({
    href,
    target: linkNewTab.value ? '_blank' : null,
    rel: linkNewTab.value && external ? 'noopener' : null,
  }).run();
  linkBar.value = false;
}
function removeLink() {
  editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
  linkBar.value = false;
}

// Selected image: size presets (share of the text column) and alt text.
// Plain functions (not computed): the editor object stays the same while its selection changes.
const imageSelected = () => isActive('image');
const imageAttrs = () => (imageSelected() ? editor.value.getAttributes('image') : {});
const imageAlt = () => imageAttrs().alt || '';
const imageAlign = () => imageAttrs().align || 'center';
const imagePositions = [
  { value: 'left', label: 'Left', title: 'Photo on the left, text wraps on the right' },
  { value: 'center', label: 'Center', title: 'Photo on its own line, centred' },
  { value: 'right', label: 'Right', title: 'Photo on the right, text wraps on the left' },
];
function setImageAlign(value) {
  const pos = editor.value.state.selection.from;
  editor.value.chain().focus().updateAttributes('image', { align: value === 'center' ? null : value }).setNodeSelection(pos).run();
  if (value === 'center') return;
  // Beside text a full-width photo leaves no room, so it starts at medium size.
  if (!Number(imageAttrs().width)) setImageSize(50);
  // Text wraps around the photo only in the paragraph that follows it, so make sure there is
  // one and put the cursor in it, ready to type.
  const { state } = editor.value;
  const node = state.doc.nodeAt(pos);
  if (!node) return;
  const after = pos + node.nodeSize;
  const next = state.doc.nodeAt(after);
  if (!next || next.type.name !== 'paragraph') {
    editor.value.chain().insertContentAt(after, { type: 'paragraph' }).setTextSelection(after + 1).focus().run();
  } else {
    editor.value.chain().setTextSelection(after + 1).focus().run();
  }
}

const imageSizes = [
  { label: 'Small', pct: 33 },
  { label: 'Medium', pct: 50 },
  { label: 'Large', pct: 75 },
  { label: 'Full', pct: 100 },
];
const columnWidth = () => editor.value?.view.dom.clientWidth - 40 || 680;
const imageSize = () => {
  const w = Number(imageAttrs().width);
  if (!w) return 100;
  const pct = Math.round((w / columnWidth()) * 100);
  return imageSizes.reduce((best, s) => (Math.abs(s.pct - pct) < Math.abs(best - pct) ? s.pct : best), 100);
};
function setImageSize(pct) {
  const width = pct >= 100 ? null : Math.round((columnWidth() * pct) / 100);
  const pos = editor.value.state.selection.from;
  editor.value.chain().focus().updateAttributes('image', { width, height: null }).setNodeSelection(pos).run();
  // The resizable image view only re-sizes itself while dragging, so apply the preset to the photo now.
  const img = editor.value.view.nodeDOM(pos)?.querySelector?.('img');
  if (img) {
    img.style.width = width ? `${width}px` : '';
    img.style.height = '';
  }
}
function setImageAlt(alt) {
  editor.value.chain().updateAttributes('image', { alt: alt.slice(0, 160) }).run();
}

const pickerOpen = ref(false);
function insertImage({ src, alt }) {
  editor.value.chain().focus().setImage({ src, alt }).run();
}

function insertTable() {
  if (isActive('table')) return;
  editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

const source = ref(false);
const sourceHtml = ref('');
function toggleSource() {
  if (!source.value) {
    sourceHtml.value = editor.value.isEmpty ? '' : editor.value.getHTML();
    source.value = true;
  } else {
    editor.value.commands.setContent(prepare(sourceHtml.value), { emitUpdate: true });
    source.value = false;
  }
}
</script>

<style scoped>
.rich-editor:focus-within { border-color: var(--a-accent); box-shadow: 0 0 0 3px var(--a-ring); }
.sep { width: 1px; height: 1.25rem; margin: 0 0.25rem; background: var(--a-border-2); }
.tbl { padding: 0.2rem 0.5rem; border-radius: 0.375rem; font-weight: 600; color: var(--a-text-2); }
.tbl:hover { background: var(--a-panel-3); }

.rich-editor-content :deep(.tiptap) { padding: 1rem 1.25rem; outline: none; min-height: inherit; font-size: 0.9375rem; line-height: 1.7; color: var(--a-text); }
.rich-editor-content :deep(.tiptap > * + *) { margin-top: 0.75em; }
.rich-editor-content :deep(h2) { font-size: 1.4rem; font-weight: 700; line-height: 1.3; margin-top: 1.4em; letter-spacing: -0.01em; }
.rich-editor-content :deep(h3) { font-size: 1.15rem; font-weight: 700; margin-top: 1.2em; }
.rich-editor-content :deep(h4) { font-size: 1rem; font-weight: 700; margin-top: 1em; }
.rich-editor-content :deep(h5) { font-size: 0.9rem; font-weight: 700; margin-top: 1em; }
.rich-editor-content :deep(h6) { font-size: 0.78rem; font-weight: 700; margin-top: 1em; text-transform: uppercase; letter-spacing: 0.06em; color: var(--a-text-2); }
.rich-editor-content :deep(h2), .rich-editor-content :deep(h3), .rich-editor-content :deep(h4), .rich-editor-content :deep(h5), .rich-editor-content :deep(h6) { color: var(--a-text); }
.rich-editor-content :deep(ul) { list-style: disc; padding-left: 1.5rem; }
.rich-editor-content :deep(ol) { list-style: decimal; padding-left: 1.5rem; }
.rich-editor-content :deep(li p) { margin: 0.15em 0; }
.rich-editor-content :deep(blockquote) { border-left: 3px solid var(--a-accent); padding-left: 1rem; color: var(--a-text-2); font-style: italic; }
.rich-editor-content :deep(a) { color: var(--a-info-text); text-decoration: underline; }
.rich-editor-content :deep(img) { max-width: 100%; height: auto; border-radius: 0.5rem; }
.rich-editor-content :deep(img.ProseMirror-selectednode) { outline: 3px solid var(--a-accent); }
.rich-editor-content :deep(.tiptap) { display: flow-root; }
.rich-editor-content :deep([data-resize-container]) { display: block !important; max-width: 100%; text-align: center; }
.rich-editor-content :deep([data-resize-container]:has(img[data-align='left'])) { float: left; clear: both; max-width: 60%; margin: 0.35em 1.25em 0.75em 0; text-align: left; }
.rich-editor-content :deep([data-resize-container]:has(img[data-align='right'])) { float: right; clear: both; max-width: 60%; margin: 0.35em 0 0.75em 1.25em; text-align: right; }
.rich-editor-content :deep(h2), .rich-editor-content :deep(h3), .rich-editor-content :deep(hr), .rich-editor-content :deep(table) { clear: both; }
.rich-editor-content :deep([data-resize-wrapper]) { display: inline-block !important; max-width: 100%; }
.rich-editor-content :deep([data-resize-handle]) { width: 12px; height: 12px; margin: -6px; border-radius: 3px; background: var(--a-accent); border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35); opacity: 0; transition: opacity 0.12s; z-index: 2; }
.rich-editor-content :deep([data-resize-handle='top-left']), .rich-editor-content :deep([data-resize-handle='bottom-right']) { cursor: nwse-resize; }
.rich-editor-content :deep([data-resize-handle='top-right']), .rich-editor-content :deep([data-resize-handle='bottom-left']) { cursor: nesw-resize; }
.rich-editor-content :deep([data-resize-wrapper]:hover [data-resize-handle]), .rich-editor-content :deep(.ProseMirror-selectednode [data-resize-handle]) { opacity: 1; }
.rich-editor-content :deep(hr) { border-color: var(--a-border-2); margin: 1.5em 0; }
.rich-editor-content :deep(table) { width: 100%; border-collapse: collapse; table-layout: fixed; }
.rich-editor-content :deep(th), .rich-editor-content :deep(td) { border: 1px solid var(--a-border-2); padding: 0.4rem 0.6rem; vertical-align: top; }
.rich-editor-content :deep(th) { background: var(--a-panel-2); font-weight: 700; text-align: left; }
.rich-editor-content :deep(.selectedCell) { background: var(--a-accent-soft); }
.rich-editor-content :deep(p.is-editor-empty:first-child::before) { content: attr(data-placeholder); float: left; color: var(--a-text-3); pointer-events: none; height: 0; }
</style>
