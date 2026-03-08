<?php 
include("../../database/connection.php");
$dbStatus = isset($conn) && !$conn->connect_error;
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Responder Medical Dashboard</title>

<link rel="stylesheet" href="assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>

</head>

<body>

<header class="topbar">
Responder Medical Monitoring
</header>

<nav id="sidebar">

<a href="roles/responder/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="api/incident.php"><i class="fa fa-triangle-exclamation"></i> Incident</a>
<a href="api/vital_live.php"><i class="fa fa-heart-pulse"></i> Vitals</a>
<a href="api/history.php"><i class="fa fa-clock-rotate-left"></i> History</a>
<a href="../../api/auth/logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>


</nav>

<main class="container">

<div class="monitor-card">

<div class="card-date" id="timeValue"></div>

<div class="patient-name" id="patientName">
Loading Patient...
</div>

<!-- HR RING (MAIN VITAL) -->
<div class="circle-wrapper">

<svg class="bp-svg" viewBox="0 0 200 200">

<circle class="bp-bg" cx="100" cy="100" r="70"/>

<circle id="bpRing"
class="bp-progress"
cx="100"
cy="100"
r="70"/>

</svg>

<div class="bp-center-text">

<div id="hrValue" class="hr-main">--</div>
<div class="hr-label">BPM</div>

</div>

</div>

<!-- BP LINE STYLE -->
<div class="bp-line-container">
<div class="bp-label">Blood Pressure</div>
<div class="bp-line">
<div id="bpLineFill"></div>
</div>
<div id="bpValue" class="bp-text">--/--</div>
</div>

<!-- OXYGEN -->
<div class="oxygen-box">
🫁 Oxygen <span id="o2Value">--</span>
</div>

<div class="patient-carousel" id="patientCarousel"></div>

</div>

</main>

<nav class="bottom-nav">

<a href="index.php" class="bottom-item">
<i class="fa fa-gauge"></i>
<span>Home</span>
</a>

<a href="api/incident.php" class="bottom-item">
<i class="fa fa-triangle-exclamation"></i>
<span>Incident</span>
</a>

<a href="api/vital_live.php" class="bottom-item">
<i class="fa fa-heart-pulse"></i>
<span>Vitals</span>
</a>

<a href="api/history.php" class="bottom-item">
<i class="fa fa-clock-rotate-left"></i>
<span>History</span>
</a>
 <a href="login.html"><i class="fa fa-right-from-bracket"></i></a>
</nav>

<script>

const patientName=document.getElementById("patientName");
const bpText=document.getElementById("bpValue");
const hrText=document.getElementById("hrValue");
const o2Text=document.getElementById("o2Value");
const timeText=document.getElementById("timeValue");
const carousel=document.getElementById("patientCarousel");
const bpLine=document.getElementById("bpLineFill");

const ring=document.getElementById("bpRing");
const circumference=440;

let patients=[];
let index=0;

function showPatient(){

if(patients.length===0) return;

let p=patients[index];

patientName.innerText=p.pat_name;

hrText.innerText=p.heart_rate;
bpText.innerText=p.bp_systolic+"/"+p.bp_diastolic;
o2Text.innerText=p.oxygen_level+" %";

let date=new Date(p.recorded_at);
timeText.innerText=date.toLocaleString();

/* HR ring animation */
let hrPercent=Math.min(p.heart_rate/180,1);

ring.style.strokeDasharray=circumference;
ring.style.strokeDashoffset=circumference*(1-hrPercent);

/* BP LINE UI */
let bpPercent=Math.min(p.bp_systolic/180,1);
bpLine.style.width=(bpPercent*100)+"%";

/* BP Color */
if(p.bp_systolic>=140) ring.style.stroke="#ef4444";
else if(p.bp_systolic>=120) ring.style.stroke="#f59e0b";
else ring.style.stroke="#22c55e";

/* Carousel highlight */
Array.from(carousel.children)
.forEach((c,i)=>c.classList.toggle("active",i===index));

index++;
if(index>=patients.length) index=0;

}

function loadPatients(){

fetch("api/bp_live.php")
.then(r=>r.json())
.then(data=>{

patients=data||[];

carousel.innerHTML="";

patients.forEach(p=>{
let div=document.createElement("div");
div.innerText=p.pat_name;
carousel.appendChild(div);
});

if(patients.length>0){
showPatient();
setInterval(showPatient,3000);
}

});

}

loadPatients();

</script>

</body>
</html>