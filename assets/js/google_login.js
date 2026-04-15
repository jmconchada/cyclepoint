import { initializeApp } from "https://www.gstatic.com/firebasejs/12.5.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/12.5.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyDKR6-2JHD_D3vvAESRumFjtgZU_AOXtzQ",
  authDomain: "cyclepoint--login.firebaseapp.com",
  projectId: "cyclepoint--login",
  storageBucket: "cyclepoint--login.firebasestorage.app",
  messagingSenderId: "198110352619",
  appId: "1:198110352619:web:b335e1805ea4fad6c2daae"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

document.addEventListener('DOMContentLoaded', () => {
  const googleLoginBtn = document.getElementById("googleLoginBtn");

  if (!googleLoginBtn) return;

  googleLoginBtn.addEventListener('click', () => {
    signInWithPopup(auth, provider)
      .then(result => {
        const user = result.user;

        fetch('google_login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            google_id: user.uid,
            name: user.displayName,
            email: user.email,
            profile_picture: user.photoURL
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            window.location.href = 'index.php';
          } else {
            alert('Login failed: ' + data.message);
          }
        })
        .catch(err => {
          console.error(err);
          alert('Error with login request.');
        });
      })
      .catch(err => {
        console.error(err);
        alert('Google login failed.');
      });
  });
});
