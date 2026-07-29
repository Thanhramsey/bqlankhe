import { createRouter, createWebHistory } from 'vue-router'
const Page={template:'<div />'}
export default createRouter({history:createWebHistory(import.meta.env.BASE_URL),routes:['/','/households','/services','/inventory','/documents','/directives/sent','/directives/inbox','/provinces','/wards','/neighborhoods','/routes','/payments','/invoices','/debts','/reports','/users','/audit-logs','/settings'].map(path=>({path,component:Page}))})
