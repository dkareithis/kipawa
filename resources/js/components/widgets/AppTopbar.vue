<template>
    <div class="w-full z-10  bg-indigo-800">

        <div class="flex">
            <div :class="visibles.length ? 'flex-none w-20' : 'flex-auto'">
                <div class="flex justify-start pt-1 pl-1 mr-2 space-x-4 cursor-pointer text-white">

                    <a @click="applist" class="w-15">
                        <i :class="$store.state.system.applist ? 'text-cyan-200' : ' text-white'"
                            class="fab fa-microsoft text-2xl "></i>

                        <div v-if="visibles.length" :class="$store.state.system.applist ? 'text-cyan-200' : 'text-white'"
                            class="inline-block text-sm font-bold  pl-1">
                            APP
                        </div>

                        <span
                            class="absolute inline-flex items-center justify-center w-4 h-4 leading-3 font-bold text-white bg-green-500 border border-white rounded-full top dark:border-gray-900 "
                            style="margin-left:-10px; font-size: 9px !important;">
                            {{ $store.state.system.menu_length }}
                        </span>
                    </a>

                    <a v-if="!visibles.length" @click="drawer" class="w-15 mr-2">
                        <i :class="$store.state.system.sidebar_show ? 'text-cyan-200' : ' text-white'"
                            class="fas fa-bars text-2xl"></i>

                    </a>

                </div>
            </div>

            <div v-if="!visibles.length" :style="'width:' + windowWidth / 4 + 'px;'"
                class="flex-none p-2 text-sm font-bold text-white truncate uppercase whitespace-nowrap">
                {{ $store.state.system.active_menu }}
            </div>

            <div v-if="visibles.length" class="flex-auto">
                <ul id="main-menu" class="flex items-left justify-left">
                    <li class="py-2">
                        <div class="inline uppercase text-white text-sm font-bold">
                            {{ $store.state.system.active_menu }}
                            <i class="fas fa-chevron-right text-sm"></i>
                        </div>
                    </li>
                    <template v-for="(visible, t_index) in visibles" :key="t_index">
                        <li v-if="countObjectKeys(visible.list)" class="group relative  py-2 px-1">
                            <a class="text-white cursor-pointer">
                                {{ visible.title }}
                                <i class="fas fa-chevron-down" style="font-size:10px;"></i>
                            </a>
                            <ul class="absolute hidden group-hover:block bg-white border shadow-l-lg mt-2 py-2 w-44 z-10">
                                <li v-for="(submenu, index) in visible.list" :key="index">

                                    <a v-if="submenu.path != ''" :href="'#' + submenu.path"
                                        class="hover:text-blue-800 p-2 block">
                                        {{ submenu.title }}
                                    </a>
                                    <hr v-else class="mx-1 border-dotted">
                                </li>
                            </ul>
                        </li>
                        <li v-else class="text-white px-1 py-2">
                            <a class="text-white" :href="'#' + visible.path">
                                {{ visible.title }}
                            </a>
                        </li>
                    </template>
                    <li id="othersMenu" :class="hiddens.length ? '' : 'hidden'" class="group relative  py-2 px-1">
                        <a class="text-white cursor-pointer">
                            More
                            <i class="fas fa-chevron-down" style="font-size:10px;"></i>
                        </a>
                        <ul class="absolute hidden group-hover:block bg-white border shadow-l-lg mt-2 py-2 w-44 z-10">
                            <template v-for="(hidden, index) in hiddens" :key="index">
                                <template v-if="countObjectKeys(hidden.list)">
                                    <li>
                                        <hr>
                                    </li>
                                    <li>
                                        <b class="text-xs bg-gray-50 block px-2 py-1">{{ hidden.title }}</b>
                                    </li>
                                    <li v-for="(submenu, index) in hidden.list" :key="index">
                                        <a v-if="submenu.path != ''" :href="'#' + submenu.path"
                                            class="hover:text-blue-800 py-1 px-2 block">
                                            {{ submenu.title }}
                                        </a>
                                        <hr v-else class="mx-1 border-dotted">
                                    </li>
                                </template>

                                <li v-else>
                                    <a :href="'#' + hidden.path" class="hover:text-blue-800 py-1 px-2 block">
                                        {{ hidden.title }}
                                    </a>
                                </li>
                            </template>

                        </ul>
                    </li>

                </ul>
            </div>

            <div :class="visibles.length ? 'flex-none w-54' : 'flex-auto'" class="text-right">
                <div class="flex justify-end pt-1 pr-1 pb-1 mr-2 space-x-2 cursor-pointer">
                    <app-topbar-icon-others></app-topbar-icon-others>
                    <app-topbar-icon-avatar></app-topbar-icon-avatar>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import AppTopbarIconAvatar from "@/components/widgets/AppTopbarIconAvatar.vue";
import AppTopbarIconOthers from "@/components/widgets/AppTopbarIconOthers.vue";

export default {
    components: {
        AppTopbarIconAvatar,
        AppTopbarIconOthers,
    },
    setup() {
        return {};
    },

    props: {
        windowWidth: { type: String, default: window.innerWidth },
        windowHeight: { type: String, default: window.innerHeight },
    },
    watch: {
        // whenever question changes, this function will run
        '$store.state.system.menu'(newer, older) {

            var active_subs_1 = this.$store.state.system.active_subs_1;

            if (active_subs_1 == null) {
                active_subs_1 = 0;
            }

            this.menus = newer[active_subs_1]['menus'];

            this.handleResize()
        },
        '$store.state.system.active_subs_1'(newer, older) {
            this.menus = this.$store.state.system.menu[newer]['menus'];
            this.handleResize()
        },
    },
    data() {
        return {
            menus: [],
            hiddens: [],
            visibles: [],
        }
    },
    methods: {
        logout() {
            this.$store.commit("auth/logout");
        },
        drawer() {
            this.$store.commit("system/sidebar_show", !this.$store.state.system.sidebar_show);
        },
        applist() {
            this.$store.commit("system/sidebar_show", false);
            this.$store.commit("system/applist_show", !this.$store.state.system.applist_show);
        },
        countObjectKeys(obj) {
            return Object.keys(obj).length;
        },
        handleResize() {
            // Handle resize event here
            //alert("handleResize");
            const availableSpace = window.innerWidth - 200 - 160;
            let totalWidth = 0;
            var visibles = [];
            var hiddens = [];

            for (const key in this.menus) {
                var item = this.menus[key];
                let itemWidth = this.measureTextWidth(item.title);
                itemWidth = itemWidth + 10;

                totalWidth += itemWidth;

                if (totalWidth <= availableSpace) {
                    visibles.push(item);
                } else {
                    hiddens.push(item);
                }
            }

            if (!visibles.length) {
                this.$store.commit("system/has_menu", true);
            } else {
                this.$store.commit("system/has_menu", false);
            }

            this.visibles = visibles;
            this.hiddens = hiddens;
        },
        measureTextWidth(text) {
            const span = document.createElement('span');

            span.style.visibility = 'hidden';
            span.style.position = 'absolute';
            span.style.whiteSpace = 'nowrap';
            //span.style.fontSize = '14px';
            span.classList.add("px-1");
            span.textContent = text;

            document.body.appendChild(span);
            const width = span.offsetWidth;
            document.body.removeChild(span);
            return width;
        }
    },
    mounted() {
        window.addEventListener('resize', this.handleResize);
        window.addEventListener('load', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
};
</script>

<style lang="scss">
.user-profile-menu-content {
    .v-list-item {
        min-height: 2.5rem !important;
    }
}
</style>
