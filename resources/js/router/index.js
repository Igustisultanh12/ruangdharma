import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import Dashboard from '../views/Dashboard.vue';
import Generate from '../views/Generate.vue';
import History from '../views/History.vue';
import Profile from '../views/Profile.vue';
import AdminDashboard from '../views/Admin/Dashboard.vue';
import AdminUsers from '../views/Admin/Users.vue';
import AdminHistory from '../views/Admin/History.vue';
import AdminSettings from '../views/Admin/Settings.vue';
import AdminMuseStatus from '../views/Admin/MuseStatus.vue';
import AdminMuseConfig from '../views/Admin/MuseConfig.vue';
const routes = [
  { path:'/', component: Dashboard, meta:{auth:true} },
  { path:'/login', component: Login },
  { path:'/generate', component: Generate, meta:{auth:true} },
  { path:'/history', component: History, meta:{auth:true} },
  { path:'/profile', component: Profile, meta:{auth:true} },
  { path:'/admin', component: AdminDashboard, meta:{auth:true, admin:true} },
  { path:'/admin/users', component: AdminUsers, meta:{auth:true, admin:true} },
  { path:'/admin/history', component: AdminHistory, meta:{auth:true, admin:true} },
  { path:'/admin/settings', component: AdminSettings, meta:{auth:true, admin:true} },
  { path:'/admin/muse', component: AdminMuseStatus, meta:{auth:true, admin:true} },
  { path:'/admin/muse-config', component: AdminMuseConfig, meta:{auth:true, admin:true} },
];
const router = createRouter({ history: createWebHistory(), routes });
router.beforeEach((to, from, next)=>{
  const token = localStorage.getItem('token');
  const user = JSON.parse(localStorage.getItem('user')||'null');
  if (to.meta.auth && !token) return next('/login');
  if (to.meta.admin && user?.role!=='admin') return next('/');
  next();
});
export default router;
