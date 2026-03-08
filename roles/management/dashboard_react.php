<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VitalWear — Management Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
  <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body, #root { height: 100%; width: 100%; }
    body {
      background: #020c18;
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      color: #c8dff0;
    }
    input, select, button, textarea { font-family: 'Inter', sans-serif; }
    input::placeholder { color: #4a6f8a; }
    select option { background: #0d1929; color: #f0f8ff; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #020c18; }
    ::-webkit-scrollbar-thumb { background: #1e3a5f; border-radius: 3px; }
    @keyframes modalIn   { from { opacity:0; transform:scale(0.96); } to { opacity:1; transform:scale(1); } }
    @keyframes pulseRing { 0%,100% { opacity:1; } 50% { opacity:0.4; } }
    @keyframes fadeIn    { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    @keyframes spin      { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
    .page-enter { animation: fadeIn 0.2s ease; }
  </style>
</head>
<body>
  <div id="root"></div>
  <script type="text/babel">
    const { useState, useEffect, useCallback, useRef } = React;

    // =========================================================================
    // API CONFIG
    // =========================================================================
    const API_BASE = "http://localhost/vitalwear-api";

    // DB_KEYS maps JS state keys to PHP API endpoints
    const DB_KEYS = {
      devices:    "vw_devices",
      responders: "vw_responders",
      rescuers:   "vw_rescuers",
      deviceLog:  "vw_device_log",
      incidents:  "vw_incidents",
      vitalStats: "vw_vitalstats",
    };

    const API_MAP = {
      vw_devices:    `${API_BASE}/devices.php`,
      vw_responders: `${API_BASE}/responders.php`,
      vw_rescuers:   `${API_BASE}/rescuers.php`,
      vw_device_log: `${API_BASE}/device_log.php`,
      vw_incidents:  `${API_BASE}/incidents.php`,
      vw_vitalstats: `${API_BASE}/vitalstats.php`,
    };

    // =========================================================================
    // FIELD MAP
    // "jsKey" → "exactColumnNameReturnedByPHP_GET"
    // The PHP GET responses use these exact key names — must match 1:1.
    // =========================================================================
    const FIELD_MAP = {
      vw_devices: {
        id:     "dev_id",
        serial: "dev_serial",
        name:   "dev_name",
        type:   "dev_type",
        status: "dev_status",
      },
      vw_responders: {
        id:             "resp_id",
        name:           "resp_name",
        email:          "resp_email",
        phone:          "resp_phone",       // PHP returns resp_phone (mapped from resp_contact)
        active:         "active",
        assignedDevice: "assigned_device",
      },
      vw_rescuers: {
        id:     "resc_id",
        name:   "resc_name",
        email:  "resc_email",
        phone:  "resc_phone",              // PHP returns resc_phone (mapped from resc_contact)
        active: "active",
      },
      vw_device_log: {
        id:             "log_id",
        deviceId:       "device_id",
        responderId:    "responder_id",
        dateAssigned:   "date_assigned",
        dateReturned:   "date_returned",
        verifiedReturn: "verified_return",
      },
      vw_incidents: {
        id:          "inc_id",
        responderId: "responder_id",
        type:        "type",
        severity:    "severity",
        status:      "status",
        date:        "date",
        location:    "location",
      },
      vw_vitalstats: {
        logId:     "log_id",
        heartRate: "heart_rate",
        spo2:      "spo2",
        bp:        "bp",
        temp:      "temp",
        timestamp: "timestamp",
      },
    };

    // Convert a PHP GET row → JS object the dashboard uses internally
    function dbRowToJs(key, row) {
      const map = FIELD_MAP[key];
      if (!map) return row;
      const out = {};
      for (const [jsKey, dbCol] of Object.entries(map)) {
        let val = row[dbCol];
        if (val === undefined) val = row[jsKey];   // fallback: already-mapped key
        if (val === undefined) val = null;
        // Coerce booleans
        if (val === "1" || val === 1)      val = true;
        if (val === "0" || val === 0)      val = false;
        if (val === "true")                val = true;
        if (val === "false")               val = false;
        // Coerce nulls
        if (val === "null" || val === "")  val = null;
        out[jsKey] = val;
      }
      return out;
    }

    // Convert JS array → array of objects using DB column names for POST body
    function jsArrayToDb(key, arr) {
      const map = FIELD_MAP[key];
      if (!map) return arr;
      return arr.map(obj => {
        const row = {};
        for (const [jsKey, dbCol] of Object.entries(map)) {
          // Send BOTH the db column name AND the js key so PHP can find it either way
          row[dbCol] = obj[jsKey] !== undefined ? obj[jsKey] : null;
          row[jsKey] = obj[jsKey] !== undefined ? obj[jsKey] : null;
        }
        return row;
      });
    }

    // Read-only tables — dashboard never writes to these (IoT devices write them)
    const READ_ONLY_KEYS = new Set(["vw_vitalstats"]);

    // Load all records from the API; fall back to seed only on error
    async function dbLoad(key, seed) {
      try {
        const url = API_MAP[key];
        if (!url) return seed;
        const res = await fetch(url, { cache: "no-cache" });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const text = await res.text();
        if (!text || text.trim() === "") return seed;
        let rows;
        try { rows = JSON.parse(text); } catch { return seed; }
        if (!Array.isArray(rows)) return seed;
        if (rows.length === 0) return seed;
        return rows.map(row => dbRowToJs(key, row));
      } catch (e) {
        console.warn(`[dbLoad] ${key} failed, using seed:`, e.message);
        return seed;
      }
    }

    // Save the full current JS array -> POST to PHP (upsert + delete orphans)
    async function dbSave(key, data) {
      if (READ_ONLY_KEYS.has(key)) return;
      const url = API_MAP[key];
      if (!url) return;
      try {
        const body = jsArrayToDb(key, Array.isArray(data) ? data : [data]);
        const res  = await fetch(url, {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify(body),
        });
        if (!res.ok) {
          const t = await res.text();
          console.warn(`[dbSave] ${key} HTTP ${res.status}:`, t);
        }
      } catch (e) {
        console.warn(`[dbSave] ${key} fetch error:`, e.message);
      }
    }

    // =========================================================================
    // usePersistentState — loads from API on mount, saves on change
    // =========================================================================
    function usePersistentState(dbKey, seed) {
      const [value, setRaw]     = useState(seed);
      const [loaded, setLoaded] = useState(false);
      const isFirstLoad = useRef(true);

      useEffect(() => {
        dbLoad(dbKey, seed).then(data => {
          setRaw(data);
          setLoaded(true);
          isFirstLoad.current = false;
        });
      }, [dbKey]);

      const setValue = useCallback((updater) => {
        setRaw(prev => {
          const next = typeof updater === "function" ? updater(prev) : updater;
          dbSave(dbKey, next);
          return next;
        });
      }, [dbKey]);

      return [value, setValue, loaded];
    }

    // =========================================================================
    // SEED DATA
    // =========================================================================
    const SEED_DEVICES = [
      { id:"DEV001", serial:"VW-2024-001", name:"VitalBand Pro",  status:"available",   type:"Wristband"   },
      { id:"DEV002", serial:"VW-2024-002", name:"VitalBand Pro",  status:"assigned",    type:"Wristband"   },
      { id:"DEV003", serial:"VW-2024-003", name:"VitalPatch X1",  status:"assigned",    type:"Chest Patch" },
      { id:"DEV004", serial:"VW-2024-004", name:"VitalBand Lite", status:"maintenance", type:"Wristband"   },
      { id:"DEV005", serial:"VW-2024-005", name:"VitalPatch X1",  status:"available",   type:"Chest Patch" },
      { id:"DEV006", serial:"VW-2024-006", name:"VitalRing S2",   status:"available",   type:"Ring Sensor" },
      { id:"DEV007", serial:"VW-2024-007", name:"VitalRing S2",   status:"assigned",    type:"Ring Sensor" },
    ];

    // =========================================================================
    // ICONS
    // =========================================================================
    const Icon = ({ name, size=18 }) => {
      const s = { width:size, height:size, fill:"none", viewBox:"0 0 24 24", stroke:"currentColor" };
      const icons = {
        dashboard:    <svg {...s} strokeWidth={1.8}><rect x="3"  y="3"  width="7" height="7" rx="1"/><rect x="14" y="3"  width="7" height="7" rx="1"/><rect x="3"  y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>,
        users:        <svg {...s} strokeWidth={1.8}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
        device:       <svg {...s} strokeWidth={1.8}><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>,
        assign:       <svg {...s} strokeWidth={1.8}><path d="M12 5v14M5 12l7 7 7-7"/></svg>,
        verify:       <svg {...s} strokeWidth={1.8}><path d="M20 6L9 17l-5-5"/></svg>,
        reports:      <svg {...s} strokeWidth={1.8}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>,
        plus:         <svg {...s} strokeWidth={2}><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>,
        edit:         <svg {...s} strokeWidth={1.8}><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>,
        close:        <svg {...s} strokeWidth={2}><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>,
        heart:        <svg {...s} strokeWidth={1.8}><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>,
        alert:        <svg {...s} strokeWidth={1.8}><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>,
        chevronRight: <svg {...s} strokeWidth={2}><polyline points="9 18 15 12 9 6"/></svg>,
        check:        <svg {...s} strokeWidth={2.5}><polyline points="20 6 9 17 4 12"/></svg>,
        pulse:        <svg {...s} strokeWidth={1.8}><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>,
        menu:         <svg {...s} strokeWidth={2}><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>,
        db:           <svg {...s} strokeWidth={1.8}><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>,
        reset:        <svg {...s} strokeWidth={1.8}><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>,
        trash:        <svg {...s} strokeWidth={1.8}><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>,
        activity:     <svg {...s} strokeWidth={1.8}><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>,
        shield:       <svg {...s} strokeWidth={1.8}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>,
        eye:          <svg {...s} strokeWidth={1.8}><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>,
        info:         <svg {...s} strokeWidth={1.8}><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>,
        refresh:      <svg {...s} strokeWidth={1.8}><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.08-8.83"/></svg>,
        download:     <svg {...s} strokeWidth={1.8}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>,
      };
      return icons[name] || null;
    };

    // =========================================================================
    // SHARED UI COMPONENTS
    // =========================================================================
    const Modal = ({ title, onClose, children, wide=false }) => (
      <div style={{position:"fixed",inset:0,zIndex:1000,display:"flex",alignItems:"center",justifyContent:"center"}}>
        <div style={{position:"absolute",inset:0,background:"rgba(2,8,20,0.88)",backdropFilter:"blur(4px)"}} onClick={onClose}/>
        <div style={{position:"relative",background:"#0d1929",border:"1px solid #1e3a5f",borderRadius:16,padding:32,
          minWidth:wide?600:460,maxWidth:wide?720:560,width:"92%",maxHeight:"90vh",overflowY:"auto",
          boxShadow:"0 24px 64px rgba(0,0,0,0.7)",animation:"modalIn 0.2s ease"}}>
          <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:24}}>
            <h2 style={{color:"#f0f8ff",fontSize:18,fontWeight:700,fontFamily:"'Syne',sans-serif",letterSpacing:"-0.01em"}}>{title}</h2>
            <button onClick={onClose} style={{background:"#1a3050",border:"1px solid #2a4f72",borderRadius:8,color:"#9ecde8",cursor:"pointer",padding:"6px 8px",display:"flex",alignItems:"center"}}>
              <Icon name="close" size={16}/>
            </button>
          </div>
          {children}
        </div>
      </div>
    );

    const Field = ({ label, children, hint }) => (
      <div style={{marginBottom:18}}>
        <label style={{display:"block",color:"#9ecde8",fontSize:12,fontWeight:600,marginBottom:7,letterSpacing:"0.07em",textTransform:"uppercase"}}>{label}</label>
        {children}
        {hint && <div style={{color:"#5d8aac",fontSize:12,marginTop:5}}>{hint}</div>}
      </div>
    );

    const Input = ({ value, onChange, placeholder, type="text", disabled=false }) => (
      <input type={type} value={value||""} onChange={e=>onChange(e.target.value)} placeholder={placeholder} disabled={disabled}
        style={{width:"100%",background:disabled?"#060f1c":"#091525",border:"1px solid #1e3a5f",borderRadius:8,color:disabled?"#4a6f8a":"#f0f8ff",padding:"11px 14px",fontSize:15,outline:"none",boxSizing:"border-box",fontFamily:"inherit",transition:"border-color 0.15s",cursor:disabled?"not-allowed":"text"}}
        onFocus={e=>!disabled && (e.target.style.borderColor="#0ea5e9")} onBlur={e=>e.target.style.borderColor="#1e3a5f"}/>
    );

    const Select = ({ value, onChange, children, disabled=false }) => (
      <select value={value||""} onChange={e=>onChange(e.target.value)} disabled={disabled}
        style={{width:"100%",background:disabled?"#060f1c":"#091525",border:"1px solid #1e3a5f",borderRadius:8,color:disabled?"#4a6f8a":"#f0f8ff",padding:"11px 14px",fontSize:15,outline:"none",boxSizing:"border-box",fontFamily:"inherit",cursor:disabled?"not-allowed":"pointer"}}>
        {children}
      </select>
    );

    const Btn = ({ onClick, children, variant="primary", size="md", disabled=false, title }) => {
      const vs = {
        primary:   {background:"linear-gradient(135deg,#0ea5e9,#0369a1)",color:"#fff",   border:"none"},
        secondary: {background:"#1a3050",                                color:"#9ecde8",border:"1px solid #2a4f72"},
        success:   {background:"linear-gradient(135deg,#10b981,#059669)",color:"#fff",   border:"none"},
        danger:    {background:"linear-gradient(135deg,#ef4444,#dc2626)",color:"#fff",   border:"none"},
        ghost:     {background:"transparent",                            color:"#9ecde8",border:"1px solid #1e3a5f"},
        warning:   {background:"linear-gradient(135deg,#f59e0b,#d97706)",color:"#fff",   border:"none"},
      };
      const ss = {
        sm:{padding:"6px 13px", fontSize:13},
        md:{padding:"10px 20px",fontSize:15},
        lg:{padding:"13px 28px",fontSize:16},
      };
      return (
        <button onClick={onClick} disabled={disabled} title={title}
          style={{...vs[variant],...ss[size],borderRadius:8,cursor:disabled?"not-allowed":"pointer",fontWeight:600,
            display:"inline-flex",alignItems:"center",gap:6,fontFamily:"'Syne',sans-serif",opacity:disabled?0.5:1,
            transition:"all 0.15s",whiteSpace:"nowrap"}}>
          {children}
        </button>
      );
    };

    const Badge = ({ label }) => {
      const map = {
        available:   {bg:"rgba(16,185,129,0.15)", text:"#34d399", border:"rgba(16,185,129,0.35)"},
        assigned:    {bg:"rgba(14,165,233,0.15)", text:"#38bdf8", border:"rgba(14,165,233,0.35)"},
        maintenance: {bg:"rgba(245,158,11,0.15)", text:"#fbbf24", border:"rgba(245,158,11,0.35)"},
        active:      {bg:"rgba(239,68,68,0.15)",  text:"#f87171", border:"rgba(239,68,68,0.35)" },
        completed:   {bg:"rgba(16,185,129,0.15)", text:"#34d399", border:"rgba(16,185,129,0.35)"},
        Critical:    {bg:"rgba(239,68,68,0.2)",   text:"#fca5a5", border:"rgba(239,68,68,0.45)" },
        High:        {bg:"rgba(245,158,11,0.2)",  text:"#fcd34d", border:"rgba(245,158,11,0.45)"},
        Medium:      {bg:"rgba(99,102,241,0.2)",  text:"#c4b5fd", border:"rgba(99,102,241,0.45)"},
        Low:         {bg:"rgba(16,185,129,0.15)", text:"#34d399", border:"rgba(16,185,129,0.35)"},
        true:        {bg:"rgba(16,185,129,0.15)", text:"#34d399", border:"rgba(16,185,129,0.35)"},
        false:       {bg:"rgba(239,68,68,0.15)",  text:"#f87171", border:"rgba(239,68,68,0.35)" },
        inactive:    {bg:"rgba(100,116,139,0.15)",text:"#94a3b8", border:"rgba(100,116,139,0.35)"},
      };
      const key = String(label);
      const c = map[key] || {bg:"rgba(255,255,255,0.08)",text:"#94a3b8",border:"rgba(255,255,255,0.15)"};
      return (
        <span style={{background:c.bg,color:c.text,border:`1px solid ${c.border}`,borderRadius:20,
          padding:"3px 11px",fontSize:12,fontWeight:700,letterSpacing:"0.05em",textTransform:"capitalize",whiteSpace:"nowrap"}}>
          {key === "true" ? "✓ Verified" : key === "false" ? "Unverified" : label}
        </span>
      );
    };

    const PageHeader = ({ title, subtitle, action }) => (
      <div style={{display:"flex",justifyContent:"space-between",alignItems:"flex-start",marginBottom:28}}>
        <div style={{flex:1,marginRight:16}}>
          <h1 style={{color:"#f0f8ff",fontSize:22,fontWeight:800,fontFamily:"'Syne',sans-serif",letterSpacing:"-0.02em",marginBottom:6}}>{title}</h1>
          <p style={{color:"#7aa8c9",fontSize:14,lineHeight:1.6}}>{subtitle}</p>
        </div>
        {action && <div style={{flexShrink:0,marginTop:2}}>{action}</div>}
      </div>
    );

    const TH = ({children, center=false}) => (
      <th style={{color:"#7aa8c9",fontSize:12,fontWeight:700,textTransform:"uppercase",letterSpacing:"0.07em",padding:"13px 18px",textAlign:center?"center":"left",whiteSpace:"nowrap",background:"#091525"}}>{children}</th>
    );

    const EmptyState = ({ message }) => (
      <div style={{color:"#7aa8c9",fontSize:14,background:"#0d1929",borderRadius:10,padding:"24px 20px",border:"1px solid #1a3354",textAlign:"center",display:"flex",alignItems:"center",justifyContent:"center",gap:8}}>
        <Icon name="info" size={15}/> {message}
      </div>
    );

    const LoadingSpinner = () => (
      <div style={{display:"flex",alignItems:"center",justifyContent:"center",padding:"40px 0",gap:12,color:"#4a6f8a"}}>
        <div style={{width:20,height:20,border:"2px solid #1a3354",borderTopColor:"#0ea5e9",borderRadius:"50%",animation:"spin 0.7s linear infinite"}}/>
        <span style={{fontSize:14}}>Loading from database…</span>
      </div>
    );

    // =========================================================================
    // DASHBOARD
    // =========================================================================
    const Dashboard = ({ devices, deviceLog, incidents, responders, rescuers, vitalStats, onNavigate }) => {
      const total        = devices.length;
      const available    = devices.filter(d=>d.status==="available").length;
      const assigned     = devices.filter(d=>d.status==="assigned").length;
      const maintenance  = devices.filter(d=>d.status==="maintenance").length;
      const notReturned  = deviceLog.filter(l=>!l.dateReturned).length;
      const activeInc    = incidents.filter(i=>i.status==="active").length;
      const completedInc = incidents.filter(i=>i.status==="completed").length;
      const activeResp   = responders.filter(r=>r.active).length;
      const activeResc   = rescuers.filter(r=>r.active).length;

      const recentLogs   = [...deviceLog].sort((a,b)=>new Date(b.dateAssigned)-new Date(a.dateAssigned)).slice(0,5);
      const activeIncidents = incidents.filter(i=>i.status==="active");

      const cards = [
        {label:"Total Devices",       val:total,        icon:"device",   color:"#0ea5e9", bg:"rgba(14,165,233,0.1)",  nav:"deviceList" },
        {label:"Available",           val:available,    icon:"check",    color:"#10b981", bg:"rgba(16,185,129,0.1)",  nav:"deviceList" },
        {label:"Assigned",            val:assigned,     icon:"assign",   color:"#f59e0b", bg:"rgba(245,158,11,0.1)",  nav:"assign"     },
        {label:"Not Yet Returned",    val:notReturned,  icon:"alert",    color:"#ef4444", bg:"rgba(239,68,68,0.1)",   nav:"verify"     },
        {label:"Active Incidents",    val:activeInc,    icon:"heart",    color:"#ec4899", bg:"rgba(236,72,153,0.1)",  nav:"reports"    },
        {label:"Completed Incidents", val:completedInc, icon:"verify",   color:"#8b5cf6", bg:"rgba(139,92,246,0.1)",  nav:"reports"    },
        {label:"Active Responders",   val:activeResp,   icon:"users",    color:"#06b6d4", bg:"rgba(6,182,212,0.1)",   nav:"responders" },
        {label:"Active Rescuers",     val:activeResc,   icon:"shield",   color:"#84cc16", bg:"rgba(132,204,22,0.1)",  nav:"rescuers"   },
        {label:"In Maintenance",      val:maintenance,  icon:"refresh",  color:"#f97316", bg:"rgba(249,115,22,0.1)",  nav:"deviceList" },
      ];

      return (
        <div className="page-enter">
          <div style={{marginBottom:28}}>
            <h1 style={{color:"#f0f8ff",fontSize:26,fontWeight:800,fontFamily:"'Syne',sans-serif",letterSpacing:"-0.02em",marginBottom:8}}>Command Overview</h1>
            <p style={{color:"#7aa8c9",fontSize:15,lineHeight:1.6,maxWidth:700}}>
              A management dashboard for the <strong style={{color:"#9ecde8"}}>VitalWear IoT Health Monitoring System</strong> — overseeing device assignments, field responder coordination, incident tracking, and real-time wearable deployment status across all sites.
            </p>
          </div>

          <div style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:14,marginBottom:24}}>
            {cards.map(c=>(
              <div key={c.label} onClick={()=>onNavigate && onNavigate(c.nav)}
                style={{background:"#0d1929",border:"1px solid #1a3354",borderRadius:14,padding:"18px 20px",position:"relative",overflow:"hidden",cursor:"pointer",transition:"border-color 0.15s,transform 0.1s"}}
                onMouseEnter={e=>{e.currentTarget.style.borderColor=c.color+"60";e.currentTarget.style.transform="translateY(-1px)";}}
                onMouseLeave={e=>{e.currentTarget.style.borderColor="#1a3354";e.currentTarget.style.transform="none";}}>
                <div style={{position:"absolute",top:0,right:0,width:72,height:72,background:c.bg,borderRadius:"0 14px 0 72px"}}/>
                <div style={{display:"flex",alignItems:"center",gap:9,marginBottom:12}}>
                  <div style={{background:c.bg,border:`1px solid ${c.color}50`,borderRadius:9,padding:7,color:c.color,display:"flex",zIndex:1}}>
                    <Icon name={c.icon} size={15}/>
                  </div>
                  <span style={{color:"#7aa8c9",fontSize:11,fontWeight:600,textTransform:"uppercase",letterSpacing:"0.06em"}}>{c.label}</span>
                </div>
                <div style={{color:c.color,fontSize:36,fontWeight:800,fontFamily:"'Syne',sans-serif",lineHeight:1}}>{c.val}</div>
              </div>
            ))}
          </div>

          <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:16,marginBottom:16}}>
            {/* Recent Assignments */}
            <div style={{background:"#0d1929",border:"1px solid #1a3354",borderRadius:14,padding:22}}>
              <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:16}}>
                <h3 style={{color:"#f0f8ff",fontSize:15,fontWeight:700,fontFamily:"'Syne',sans-serif"}}>Recent Assignments</h3>
                <button onClick={()=>onNavigate("reports")} style={{background:"none",border:"none",color:"#0ea5e9",cursor:"pointer",fontSize:13,fontWeight:600}}>View all →</button>
              </div>
              {recentLogs.length===0
                ? <EmptyState message="No assignment records found."/>
                : recentLogs.map(log=>{
                  const dev = devices.find(d=>d.id===log.deviceId);
                  const res = responders.find(r=>r.id===log.responderId);
                  return (
                    <div key={log.id} style={{display:"flex",justifyContent:"space-between",alignItems:"center",padding:"10px 0",borderBottom:"1px solid #0f2035"}}>
                      <div>
                        <div style={{color:"#f0f8ff",fontSize:14,fontWeight:600,marginBottom:1}}>
                          {dev?.name||log.deviceId} <span style={{color:"#5d8aac",fontWeight:400,fontSize:12}}>#{dev?.serial||"—"}</span>
                        </div>
                        <div style={{color:"#7aa8c9",fontSize:12}}>{res?.name||"Unknown"} · {log.dateAssigned}</div>
                      </div>
                      <Badge label={log.dateReturned?"completed":"active"}/>
                    </div>
                  );
                })}
            </div>

            {/* Active Incidents */}
            <div style={{background:"#0d1929",border:"1px solid #1a3354",borderRadius:14,padding:22}}>
              <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:16}}>
                <h3 style={{color:"#f0f8ff",fontSize:15,fontWeight:700,fontFamily:"'Syne',sans-serif"}}>Live Incidents</h3>
                <button onClick={()=>onNavigate("reports")} style={{background:"none",border:"none",color:"#0ea5e9",cursor:"pointer",fontSize:13,fontWeight:600}}>View all →</button>
              </div>
              {incidents.length===0
                ? <EmptyState message="No incidents recorded."/>
                : incidents.slice(0,5).map(inc=>{
                  const res = responders.find(r=>r.id===inc.responderId);
                  return (
                    <div key={inc.id} style={{display:"flex",justifyContent:"space-between",alignItems:"center",padding:"10px 0",borderBottom:"1px solid #0f2035"}}>
                      <div>
                        <div style={{color:"#f0f8ff",fontSize:14,fontWeight:600,marginBottom:1}}>{inc.type}</div>
                        <div style={{color:"#7aa8c9",fontSize:12}}>{inc.location} · {res?.name||"Unknown"} · {inc.date}</div>
                      </div>
                      <div style={{display:"flex",gap:5,alignItems:"center",flexShrink:0}}>
                        <Badge label={inc.severity}/>
                        <Badge label={inc.status}/>
                      </div>
                    </div>
                  );
                })}
            </div>
          </div>

          {/* Vital Stats */}
          {vitalStats.length > 0 && (
            <div style={{background:"#0d1929",border:"1px solid #1a3354",borderRadius:14,padding:22}}>
              <h3 style={{color:"#f0f8ff",fontSize:15,fontWeight:700,fontFamily:"'Syne",sans-serif",marginBottom:16}}>Latest Vital Readings</h3>
              <div style={{display:"grid",gridTemplateColumns:"repeat(auto-fill,minmax(220px,1fr))",gap:12}}>
                {vitalStats.map((vs,i)=>{
                  const log = deviceLog.find(l=>l.id===vs.logId);
                  const res = responders.find(r=>r.id===log?.responderId);
                  return (
                    <div key={i} style={{background:"#091525",borderRadius:10,padding:16,border:"1px solid #1a3354"}}>
                      <div style={{color:"#9ecde8",fontSize:12,fontWeight:600,marginBottom:10}}>{res?.name||"Unknown"} · {vs.timestamp}</div>
                      <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:8}}>
                        {[["❤️ HR",`${vs.heartRate} bpm`,"#ef4444"],[`🫁 SpO₂`,`${vs.spo2}%`,"#0ea5e9"],["🩸 BP",vs.bp,"#8b5cf6"],["🌡️ Temp",`${vs.temp}°C`,"#f59e0b"]].map(([k,v,c])=>(
                          <div key={k} style={{textAlign:"center",background:"#0d1929",borderRadius:7,padding:"8px 6px",border:"1px solid #1a3354"}}>
                            <div style={{color:c,fontWeight:700,fontSize:14,fontFamily:"'Syne',sans-serif"}}>{v}</div>
                            <div style={{color:"#5d8aac",fontSize:11,marginTop:2}}>{k}</div>
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </div>
      );
    };

    // =========================================================================
    // APP ROOT
    // =========================================================================
    function App() {
      const [page,       setPage]     = useState("dashboard");
      const [sideOpen,   setSideOpen] = useState(true);
      const [expanded,   setExpanded] = useState({users:true,devices:true,manage:true});
      const [resetModal, setResetModal] = useState(false);
      const [savedFlash, setSavedFlash] = useState(false);

      // All state loaded from DB (via API)
      const [devices,    setDevices,    devLoaded]  = usePersistentState(DB_KEYS.devices,    SEED_DEVICES);
      const [responders, setResponders, respLoaded] = usePersistentState(DB_KEYS.responders, []);
      const [rescuers,   setRescuers,   rescLoaded] = usePersistentState(DB_KEYS.rescuers,   []);
      const [deviceLog,  setDeviceLog,  logLoaded]  = usePersistentState(DB_KEYS.deviceLog,  []);
      const [incidents,  setIncidents,  incLoaded]  = usePersistentState(DB_KEYS.incidents,  []);
      const [vitalStats, setVitalStats, vsLoaded]   = usePersistentState(DB_KEYS.vitalStats, []);

      const allLoaded = devLoaded && respLoaded && rescLoaded && logLoaded && incLoaded && vsLoaded;

      // Saved flash on any state change
      const saveTimer = useRef(null);
      useEffect(()=>{
        if(!allLoaded) return;
        setSavedFlash(true);
        if(saveTimer.current) clearTimeout(saveTimer.current);
        saveTimer.current = setTimeout(()=>setSavedFlash(false), 2200);
      }, [devices, responders, rescuers, deviceLog, incidents]);

      const resetAll = () => {
        setDevices(SEED_DEVICES);
        setResponders([]);
        setRescuers([]);
        setDeviceLog([]);
        setIncidents([]);
        setResetModal(false);
        setPage("dashboard");
      };

      const nav = [
        {id:"dashboard", icon:"dashboard", label:"Dashboard"},
        {id:"users",     icon:"users",     label:"User Management", children:[
          {id:"responders", label:"Manage Responders"},
          {id:"rescuers",   label:"Manage Rescuers"},
        ]},
        {id:"devices", icon:"device", label:"Device Management", children:[
          {id:"deviceList",     label:"Device List"},
          {id:"registerDevice", label:"Register Device"},
        ]},
        {id:"manage", icon:"activity", label:"Operations", children:[
          {id:"incidents", label:"Manage Incidents"},
          {id:"assign",    label:"Assign Device"},
          {id:"verify",    label:"Verify Return"},
        ]},
        {id:"reports", icon:"reports", label:"Reports"},
      ];

      const navigate = (page) => setPage(page);

      const renderContent = () => {
        switch(page) {
          case "responders":    return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Responder management coming soon...</div>;
          case "rescuers":      return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Rescuer management coming soon...</div>;
          case "deviceList":
          case "registerDevice": return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Device management coming soon...</div>;
          case "incidents":     return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Incident management coming soon...</div>;
          case "assign":        return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Device assignment coming soon...</div>;
          case "verify":        return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Device verification coming soon...</div>;
          case "reports":       return <div style={{color:"#f0f8ff",fontSize:16,padding:20}}>Reports coming soon...</div>;
          default:              return <Dashboard        devices={devices} deviceLog={deviceLog} incidents={incidents} responders={responders} rescuers={rescuers} vitalStats={vitalStats} onNavigate={navigate}/>;
        }
      };

      return (
        <div style={{display:"flex",height:"100vh",background:"#020c18",fontFamily:"'Inter',sans-serif",overflow:"hidden"}}>

          {/* SIDEBAR */}
          <div style={{width:sideOpen?252:0,minWidth:sideOpen?252:0,background:"#050e1c",borderRight:"1px solid #0d1e35",display:"flex",flexDirection:"column",transition:"all 0.25s",overflow:"hidden",flexShrink:0}}>
            {/* Logo */}
            <div style={{padding:"20px 18px 16px",borderBottom:"1px solid #0d1e35"}}>
              <div style={{display:"flex",alignItems:"center",gap:11}}>
                <div style={{width:36,height:36,borderRadius:11,background:"linear-gradient(135deg,#0ea5e9,#0284c7)",display:"flex",alignItems:"center",justifyContent:"center",color:"#fff",flexShrink:0}}>
                  <Icon name="pulse" size={18}/>
                </div>
                <div>
                  <div style={{color:"#f0f8ff",fontWeight:800,fontSize:16,fontFamily:"'Syne',sans-serif",lineHeight:1.2}}>VitalWear</div>
                  <div style={{color:"#0ea5e9",fontSize:11,fontWeight:600,letterSpacing:"0.1em",textTransform:"uppercase"}}>Management</div>
                </div>
              </div>
            </div>

            {/* Nav */}
            <nav style={{flex:1,overflowY:"auto",padding:"12px 8px"}}>
              {nav.map(item=>{
                const isActive = page===item.id || (item.children && item.children.some(c=>c.id===page));
                return (
                  <div key={item.id}>
                    <button onClick={()=>{ if(item.children) setExpanded(e=>({...e,[item.id]:!e[item.id]})); else setPage(item.id); }}
                      style={{width:"100%",display:"flex",alignItems:"center",justifyContent:"space-between",padding:"9px 12px",borderRadius:9,border:"none",cursor:"pointer",marginBottom:2,transition:"all 0.15s",textAlign:"left",
                        background:isActive&&!item.children?"rgba(14,165,233,0.13)":"transparent",
                        color:isActive?"#38bdf8":"#8cb8d4"}}>
                      <div style={{display:"flex",alignItems:"center",gap:10}}>
                        <span style={{opacity:isActive?1:0.6,display:"flex"}}><Icon name={item.icon} size={15}/></span>
                        <span style={{fontSize:13,fontWeight:600,whiteSpace:"nowrap"}}>{item.label}</span>
                      </div>
                      {item.children && (
                        <span style={{transform:expanded[item.id]?"rotate(90deg)":"none",transition:"transform 0.2s",display:"flex",color:"#5d8aac"}}>
                          <Icon name="chevronRight" size={12}/>
                        </span>
                      )}
                    </button>
                    {item.children && expanded[item.id] && (
                      <div style={{marginLeft:16,marginBottom:4}}>
                        {item.children.map(c=>(
                          <button key={c.id} onClick={()=>setPage(c.id)}
                            style={{width:"100%",display:"flex",alignItems:"center",gap:9,padding:"7px 11px",borderRadius:7,border:"none",cursor:"pointer",marginBottom:1,fontSize:13,fontWeight:600,textAlign:"left",transition:"all 0.12s",
                              background:page===c.id?"rgba(14,165,233,0.1)":"transparent",color:page===c.id?"#38bdf8":"#6b9fc4"}}>
                            <span style={{width:5,height:5,borderRadius:"50%",flexShrink:0,background:page===c.id?"#0ea5e9":"#1e3a5f"}}/>
                            {c.label}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </nav>

            {/* Sidebar Footer */}
            <div style={{padding:"12px 14px",borderTop:"1px solid #0d1e35"}}>
              <div style={{display:"flex",alignItems:"center",justifyContent:"space-between"}}>
                <div style={{display:"flex",alignItems:"center",gap:7}}>
                  <div style={{width:6,height:6,borderRadius:"50%",background:"#10b981",animation:"pulseRing 2s infinite"}}/>
                  <span style={{color:"#6b9fc4",fontSize:12,fontWeight:600}}>System Online</span>
                </div>
                <div style={{display:"flex",alignItems:"center",gap:5,color:"#38bdf8",fontSize:12,fontWeight:600,background:"rgba(14,165,233,0.08)",border:"1px solid rgba(14,165,233,0.2)",borderRadius:6,padding:"2px 7px",
                  opacity:savedFlash?1:0,transition:"opacity 0.5s"}}>
                  <Icon name="db" size={10}/>Saved
                </div>
              </div>
              {!allLoaded && (
                <div style={{marginTop:8,display:"flex",alignItems:"center",gap:6,color:"#4a6f8a",fontSize:12}}>
                  <div style={{width:12,height:12,border:"2px solid #1a3354",borderTopColor:"#0ea5e9",borderRadius:"50%",animation:"spin 0.7s linear infinite"}}/>
                  Loading from API…
                </div>
              )}
            </div>
          </div>

          {/* MAIN */}
          <div style={{flex:1,display:"flex",flexDirection:"column",overflow:"hidden"}}>
            {/* Topbar */}
            <div style={{height:54,background:"#050e1c",borderBottom:"1px solid #0d1e35",display:"flex",alignItems:"center",justifyContent:"space-between",padding:"0 22px",flexShrink:0}}>
              <div style={{display:"flex",alignItems:"center",gap:11}}>
                <button onClick={()=>setSideOpen(o=>!o)}
                  style={{background:"#0d1929",border:"1px solid #1a3354",borderRadius:7,color:"#9ecde8",cursor:"pointer",padding:"6px 8px",display:"flex",alignItems:"center"}}>
                  <Icon name="menu" size={15}/>
                </button>
                <span style={{color:"#4a6f8a",fontSize:13}}>VitalWear IoT Health Monitoring <span style={{color:"#2a4f72"}}>— Management Portal</span></span>
              </div>
              <div style={{display:"flex",alignItems:"center",gap:8}}>
                <div style={{display:"flex",alignItems:"center",gap:5,background:"#0d1929",border:"1px solid #1a3354",borderRadius:7,padding:"5px 11px"}}>
                  <span style={{color:"#7aa8c9",display:"flex"}}><Icon name="db" size={12}/></span>
                  <span style={{color:"#7aa8c9",fontSize:12,fontWeight:500}}>MySQL API</span>
                  <span style={{width:5,height:5,borderRadius:"50%",background:allLoaded?"#10b981":"#f59e0b",animation:"pulseRing 2s infinite"}}/>
                </div>
                <div style={{display:"flex",alignItems:"center",gap:5,background:"#0d1929",border:"1px solid #1a3354",borderRadius:7,padding:"5px 11px"}}>
                  <span style={{color:"#38bdf8",display:"flex"}}><Icon name="pulse" size={12}/></span>
                  <span style={{color:"#38bdf8",fontSize:12,fontWeight:600}}>IoT Live</span>
                  <span style={{width:5,height:5,borderRadius:"50%",background:"#10b981",animation:"pulseRing 1.5s infinite"}}/>
                </div>
                <button onClick={()=>setResetModal(true)} title="Reset all data to defaults"
                  style={{background:"rgba(239,68,68,0.08)",border:"1px solid rgba(239,68,68,0.2)",borderRadius:7,color:"#f87171",cursor:"pointer",padding:"6px 8px",display:"flex",alignItems:"center"}}>
                  <Icon name="reset" size={14}/>
                </button>
                <div style={{width:32,height:32,borderRadius:"50%",background:"linear-gradient(135deg,#0ea5e9,#0284c7)",display:"flex",alignItems:"center",justifyContent:"center",color:"#fff",fontWeight:700,fontSize:13,flexShrink:0}}>M</div>
              </div>
            </div>

            {/* Page content */}
            <div style={{flex:1,overflowY:"auto",padding:26}}>
              {renderContent()}
            </div>
          </div>

          {/* RESET MODAL */}
          {resetModal && (
            <Modal title="Reset All Data" onClose={()=>setResetModal(false)}>
              <p style={{color:"#9ecde8",fontSize:15,lineHeight:1.6,marginBottom:8}}>
                This will restore all data to the original seed records and re-save them to the API.
              </p>
              <p style={{color:"#7aa8c9",fontSize:13,lineHeight:1.6,marginBottom:24}}>
                All CRUD changes — added responders, registered devices, assignment logs, and return verifications — will be permanently overwritten.
              </p>
              <div style={{display:"flex",gap:10,justifyContent:"flex-end"}}>
                <Btn onClick={()=>setResetModal(false)} variant="ghost">Cancel</Btn>
                <Btn onClick={resetAll} variant="danger"><Icon name="reset" size={14}/>Reset All Data</Btn>
              </div>
            </Modal>
          )}
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById("root")).render(<App/>);
  </script>
</body>
</html>
