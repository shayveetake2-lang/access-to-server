<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Website</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tab-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            padding: 10px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .tab.active {
            background: rgba(59, 130, 246, 0.2);
            color: white;
            border-color: rgba(59, 130, 246, 0.5);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: 100px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            transition: all 0.2s ease;
        }
        .file-upload-wrapper:hover {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        #success-message a {
            color: #3b82f6;
            text-decoration: none;
            border-bottom: 1px solid #3b82f6;
        }
    </style>
</head>
<body>
    <nav class="global-nav">
        <a href="index.html">Deployer</a>
        <a href="host.php" class="active">Host Website</a>
        <a href="movies.html">Movie Portal</a>
        <a href="debug.php">Diagnostics</a>
        <a href="auto_debug.php">Auto Debug</a>
        <a href="help.html">Help</a>
    </nav>

    <div class="background">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
        <div class="orb orb3"></div>
    </div>
    
    <div class="form-container">
        <h2>Host Website</h2>
        <div id="success-message" style="display: none; text-align: center;">
            <h3 style="color: #4ade80; margin-bottom: 10px;">Deployment Successful!</h3>
            <p id="success-text" style="color: #f1f5f9; margin-bottom: 20px;"></p>
            <a href="#" id="live-link" target="_blank" style="display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; border-radius: 8px; text-decoration: none; font-weight: 500;">Visit Live Site</a>
            <br><br>
            <a href="#" onclick="location.reload()">Deploy Another</a>
        </div>
        
        <form id="deploy-form">
            <div class="input-group">
                <input type="text" id="project_name" name="project_name" required placeholder="Project Name (e.g. my-app)" pattern="[a-zA-Z0-9-_]+" title="Only letters, numbers, dashes, and underscores allowed">
            </div>

            <div class="tab-container">
                <div class="tab active" onclick="switchTab('github')">GitHub Repo</div>
                <div class="tab" onclick="switchTab('zip')">Upload .zip</div>
            </div>

            <input type="hidden" id="deploy_method" name="deploy_method" value="github">

            <div id="github-content" class="tab-content active">
                <div class="input-group">
                    <input type="url" id="github_url" name="github_url" placeholder="https://github.com/user/repo.git">
                </div>
            </div>

            <div id="zip-content" class="tab-content">
                <div class="input-group">
                    <div class="file-upload-wrapper">
                        <span id="file-name">Click or drag a .zip file here</span>
                        <input type="file" id="zip_file" name="zip_file" accept=".zip">
                    </div>
                </div>
            </div>

            <button type="submit" id="submit-btn">Deploy Now</button>
        </form>
    </div>

    <script>
        function switchTab(method) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`.tab-container .tab:nth-child(${method === 'github' ? 1 : 2})`).classList.add('active');
            document.getElementById(`${method}-content`).classList.add('active');
            document.getElementById('deploy_method').value = method;
            
            if (method === 'github') {
                document.getElementById('github_url').required = true;
                document.getElementById('zip_file').required = false;
            } else {
                document.getElementById('github_url').required = false;
                document.getElementById('zip_file').required = true;
            }
        }
        
        // Default requirement
        document.getElementById('github_url').required = true;

        document.getElementById('zip_file').addEventListener('change', function(e) {
            if (this.files[0]) {
                document.getElementById('file-name').innerText = this.files[0].name;
            }
        });

        document.getElementById('deploy-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submit-btn');
            btn.innerText = 'Deploying...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('api/process_deployment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('deploy-form').style.display = 'none';
                    document.getElementById('success-message').style.display = 'block';
                    document.getElementById('success-text').innerText = data.message;
                    document.getElementById('live-link').href = data.url;
                } else {
                    alert('Error: ' + data.message);
                    btn.innerText = 'Deploy Now';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Deployment failed. Please check the network connection and server logs.');
                console.error('Error:', error);
                btn.innerText = 'Deploy Now';
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
