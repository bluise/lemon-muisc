import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router.js'
import './styles/global.css'
import './utils/theme.js'
import { installAppTooltip } from './utils/tooltip.js'

installAppTooltip()
createApp(App).use(router).mount('#app')
