import Confetti from '@/vue/Confetti.vue';

// Create our vue instance
const vm = new Vue({
    el: "#cp-nav-content",
    delimiters: ["${", "}"],
    components: {
        'confetti': Confetti,
    },
    data: {
    },
    methods: {
    },
    mounted() {
    },
});

// Accept HMR as per: https://webpack.js.org/api/hot-module-replacement#accept
if (module.hot) {
    module.hot.accept();
}
