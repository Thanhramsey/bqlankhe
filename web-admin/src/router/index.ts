import { createRouter, createWebHistory } from 'vue-router'
const Page={template:'<div />'}
export default createRouter({history:createWebHistory(import.meta.env.BASE_URL),routes:['/','/households','/services','/provinces','/wards','/neighborhoods','/routes','/payments','/users','/settings'].map(path=>({path,component:Page}))})
