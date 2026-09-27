import re

with open('js/admin_auth.js', 'r') as f:
    content = f.read()

# Update checkAuthOnLoad to read user_role from localStorage
auth_old = """    const storedStorage = localStorage.getItem('user_storage');
    if (storedStorage) {
        try { updateUserStorageUI(JSON.parse(storedStorage)); } catch(e) {}
    }

    if (!activeToken) {"""

auth_new = """    const storedStorage = localStorage.getItem('user_storage');
    const storedRole = localStorage.getItem('user_role');
    if (storedStorage) {
        try { updateUserStorageUI(JSON.parse(storedStorage)); } catch(e) {}
    }

    if (!activeToken) {"""

content = content.replace(auth_old, auth_new)

jwt_old = """        if (payloadObj.role) jwtRole = payloadObj.role;
        if (payloadObj.username) jwtUser = payloadObj.username;"""

jwt_new = """        if (payloadObj.role) jwtRole = payloadObj.role;
        else if (storedRole) jwtRole = storedRole;
        if (payloadObj.username) jwtUser = payloadObj.username;"""

content = content.replace(jwt_old, jwt_new)

# Add explicit button targeting in updateAdminUI
ui_old = """        if (role.toLowerCase() === 'admin') {
            document.querySelectorAll('.auth-admin-nav').forEach(el => el.classList.remove('hidden'));
        } else {
            document.querySelectorAll('.auth-admin-nav').forEach(el => el.classList.add('hidden'));
        }"""

ui_new = """        if (role.toLowerCase() === 'admin') {
            document.querySelectorAll('.auth-admin-nav').forEach(el => el.classList.remove('hidden'));
            
            // Explicitly reveal Ampache Launch buttons
            const ampacheCard = document.getElementById('ampache-launch-card');
            const ampacheBtn = document.getElementById('ampache-launch-btn');
            if (ampacheCard) ampacheCard.classList.remove('hidden');
            if (ampacheBtn) ampacheBtn.classList.remove('hidden');
        } else {
            document.querySelectorAll('.auth-admin-nav').forEach(el => el.classList.add('hidden'));
            
            // Explicitly hide Ampache Launch buttons for guests/members
            const ampacheCard = document.getElementById('ampache-launch-card');
            const ampacheBtn = document.getElementById('ampache-launch-btn');
            if (ampacheCard) ampacheCard.classList.add('hidden');
            if (ampacheBtn) ampacheBtn.classList.add('hidden');
        }"""

content = content.replace(ui_old, ui_new)

with open('js/admin_auth.js', 'w') as f:
    f.write(content)
