import { defineStore } from 'pinia'
import { api } from '@/api'
export type Profile = { id:number; username:string; name:string; email:string; phone?:string|null; date_of_birth?:string|null; gender?:string|null; identity_number?:string|null; address?:string|null; avatar_url?:string|null; permissions:string[]; menus:Array<{id:number;name:string;path:string;icon:string}> }
export const useAuthStore = defineStore('auth', {
 state: () => ({ user: null as Profile | null, loading: false }),
 actions: {
  async login(identifier:string,password:string){this.loading=true;try{const r=await api<{token:string;user:Profile}>('/auth/login',{method:'POST',body:JSON.stringify({identifier,password})});localStorage.setItem('token',r.data.token);this.user=r.data.user}catch(e){localStorage.removeItem('token');throw e}finally{this.loading=false}},
  async restore(){if(!localStorage.getItem('token'))return;try{this.user=(await api<Profile>('/auth/me')).data}catch{localStorage.removeItem('token')}},
  async logout(){try{await api('/auth/logout',{method:'POST'})}finally{localStorage.removeItem('token');this.user=null}},
 }
})
