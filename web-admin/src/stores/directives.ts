import { defineStore } from 'pinia'
import { api } from '@/api'

type RecentDirective={id:number;code:string;title:string;created_at:string;creator?:{name:string}}
export const useDirectiveStore=defineStore('directives',{state:()=>({unread:0,recent:[] as RecentDirective[],loading:false}),actions:{async refresh(){if(!localStorage.getItem('token'))return;this.loading=true;try{const result=await api<{count:number;items:RecentDirective[]}>('/directives/unread');this.unread=result.data.count;this.recent=result.data.items}catch{this.unread=0;this.recent=[]}finally{this.loading=false}}}})
