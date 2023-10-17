<?php
//your db config
require('../private/dbconfig.php');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 30 * 1024 * 1024) { // 30 MB
        echo json_encode(['error' => 'not bigger than 30 MB']);
        exit;
    }

    if (isset($_POST["encryptedNote"])) {
        $content = $_POST["encryptedNote"];

        $unique_link = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT INTO notes (note_content, unique_link) VALUES (?, ?)");
        $stmt->execute([$content, $unique_link]);

        echo json_encode(['link' => "https://" . $_SERVER['HTTP_HOST'] . "/?link=" . $unique_link]);
        exit;
    }

    if (isset($_POST["linkToFetch"])) {
        $link = $_POST["linkToFetch"];
        $stmt = $pdo->prepare("SELECT note_content FROM notes WHERE unique_link = ?");
        $stmt->execute([$link]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($note) {
            $encryptedMessage = htmlspecialchars($note["note_content"]);
            echo json_encode(['note' => $encryptedMessage]);

            $deleteStmt = $pdo->prepare("DELETE FROM notes WHERE unique_link = ?");
            $deleteStmt->execute([$link]);
        } else {
            echo json_encode(['error' => 'Note not found.']);
        }

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <link rel="icon" type="image/x-icon" sizes="16x16" href="images/favicon-16x16.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sichere Einmal-Notiz</title>
    <!-- Update with sources-->
    <script src="js/crypto-js.js"></script>
    <link href="css/tailwind.min.css" rel="stylesheet">
</head>
<style>
    #loadingIndicator {
        padding-top: 2em;
        justify-content: center !important;
        align-items: center !important;
        height: 100px;
    }

    .loader {
        border: 8px solid #f3f3f3;
        border-top: 8px solid #3498db;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>


<body class="bg-gray-100 flex flex-col justify-center min-h-screen">

    <div class="bg-white p-8 mx-auto text-center rounded-lg shadow-md w-full max-w-md">

        <div id="createNote">
            <textarea id="noteContent" rows="5" class="w-full p-3 border rounded mb-4"
                placeholder="Geb deine Notiz hier ein..."></textarea>
            <div class="flex items-center space-x-4 pb-5 ml-10">
                <input type="file" id="fileInput" accept="image/*" class="hidden" onchange="checkFileSize(this)" />
                <label for="fileInput"
                    class="cursor-pointer bg-blue-500 text-white px-4 text-sm rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Choose image
                </label>
                <span id="selectedFileName" class="text-gray-600 text-sm">no image selected</span>
            </div>
            <button onclick="saveNote()" id="saveNoteButton"
                class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">Safe note</button>
            <div id="loadingIndicator" style="display: none;">
                <div class="loader"></div>
            </div>

        </div>

        <div id="linkDisplay" style="display: none;">
            <input type="text" id="generatedLink" class="w-full p-3 border rounded mb-4" readonly>
            <button onclick="copyLink()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">copy link</button>
        </div>

        <div id="noteAction" style="display: none;">
            <button onclick="showNote()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">View and delete note</button>
        </div>

        <div id="noteDisplay" style="display: none;">
            <div class="mb-4 font-bold text-blue-400">Content of note</div>
            <div id="decryptedNote" class="mb-4 font-bold"></div>
            <img style="display: none;" id="picTag" src="" height=100>
        </div>

        <div id="backToStart" style="display: none;">
            <button onclick="redirectToPrivnote()"
                class="bg-blue-500 hover:bg-blue-600 mt-5 text-white py-2 px-4 rounded">to startpage</button>
        </div>

        <div id="errorInfo" class="pt-10" style="display: none;">
            <div class="mb-4 font-bold text-red-400">Die Notiz wurde nicht gefunden, schon gelesen oder hat nie
                existiert</div>
        </div>

        <!-- Info Button and Info-Windows -->
        <button id="infoButton" class="mt-5 hover:text-blue-800 font-bold text-gray-400 mb-4" onclick="toggleInfo()">How does it work?</button>

        <div id="infoBox" class="border p-4 mb-4 rounded bg-gray-100" style="display: none;">
            <h2 class="font-bold mb-2">How does it work?</h2>
            <p>
            The system allows the generation of a note that can be viewed only once. Immediately after
                the note is encrypted before it is sent to the server. The
                decryption key is not known to the server at any time. This ensures that no person,
                including server administrators, has access to the decrypted content of the note.
            </p>
            <p class="mt-2">
            When the provided link is accessed and the ad button is clicked, the note will be
                is retrieved and immediately deleted from the server. Since the decryption key is known only in the user's original
                only known in the user's original browser, only that browser can decrypt and display the note. On this
                No cookies are used on this page. If the link provided is lost, there is absolutely no way to recover the note.
                there is absolutely no way to recover or restore the note
                or to
                decrypt it.
            </p>
            <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-2 mt-4 rounded"
                onclick="toggleInfo()">Close</button>
        </div>


    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let urlParams = new URLSearchParams(window.location.search);
            let keyFragment = window.location.hash.substring(1);

            if (urlParams.has('link') && keyFragment) {
                document.getElementById('createNote').style.display = "none";
                document.getElementById('noteAction').style.display = "block";
            }
            else if (urlParams.has('link') && !keyFragment) {
                document.getElementById('errorInfo').innerHTML = "<div class=\"mb-4 font-bold text-red-400\">Fehler: Der Schlüssel wurde nicht bereitgestellt.</div>";
                document.getElementById('errorInfo').style.display = "block";
                document.getElementById('noteAction').style.display = "none";
            }
        });

        function saveNote() {
            showLoadingIndicator();
            document.getElementById('saveNoteButton').disabled = true;
            let noteContent = document.getElementById('noteContent').value;
            let key = CryptoJS.lib.WordArray.random(16).toString();
            //Check if img attached is valid b64
            if (/^[A-Za-z0-9+/]+={0,2}$/.test(base64String) && base64String.length % 4 === 0) {
                console.log("base64String ist ein gültiger Base64-String");
                noteContent = noteContent + "[[img]]" + base64String;
            } else {
                console.log("base64String ist KEIN gültiger Base64-String");
            }
            let encryptedNote = CryptoJS.AES.encrypt(noteContent, key).toString();
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "index.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    hideLoadingIndicator();
                    let response = JSON.parse(this.responseText);
                    let link = response.link;
                    document.getElementById('generatedLink').value = link + "#" + key;
                    document.getElementById('createNote').style.display = "none";
                    document.getElementById('linkDisplay').style.display = "block";
                }
            };
            xhr.send("encryptedNote=" + encodeURIComponent(encryptedNote));
        }

        function showNote() {
            let link = new URLSearchParams(window.location.search).get('link');
            let key = window.location.hash.substring(1);
            if (key) { // Check if key is submitted
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "index.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (this.readyState === 4 && this.status === 200) {
                        let response = JSON.parse(this.responseText);
                        if (response.note) {
                            let decryptedNote = CryptoJS.AES.decrypt(response.note, key).toString(CryptoJS.enc.Utf8);
                            decryptedNote = decryptedNote.replace(/\n/g, '<br>');
                            if (decryptedNote.includes("[[img]]")) {
                                let parts = decryptedNote.split("[[img]]");
                                let textPart = parts[0];
                                let base64Image = parts[1];
                                document.getElementById("picTag").src = "data:image/png;base64," + base64Image;
                                document.getElementById('picTag').style.display = "block";
                                document.getElementById('decryptedNote').innerHTML = textPart;
                            } else {
                                console.log(".");
                                document.getElementById('decryptedNote').innerHTML = decryptedNote;
                            }
                            document.getElementById('noteAction').style.display = "none";
                            document.getElementById('noteDisplay').style.display = "block";
                            document.getElementById('backToStart').style.display = "block";
                        } else {
                            document.getElementById('errorInfo').style.display = "block";
                            document.getElementById('backToStart').style.display = "block";

                        }
                    }
                };
                xhr.send("linkToFetch=" + link);
            } else {
                // if no key...
                document.getElementById('errorInfo').innerHTML = "<div class=\"mb-4 font-bold text-red-400\">Error... something with key...</div>";
                document.getElementById('errorInfo').style.display = "block";
            }
        }

        function redirectToPrivnote() {
            //your start page including http(s)://
            window.location.href = "<YOUR START PAGE>";
        }

        function copyLink() {
            let linkField = document.getElementById('generatedLink');
            linkField.select();
            document.execCommand("copy");
        }

        function toggleInfo() {
            let infoBox = document.getElementById('infoBox');
            if (infoBox.style.display === "none") {
                infoBox.style.display = "block";
            } else {
                infoBox.style.display = "none";
            }
        }

        var base64String = "";
        function loadImage(fileInput) {
            return new Promise((resolve, reject) => {
                var reader = new FileReader();

                reader.onload = function () {
                    base64String = reader.result.replace("data:", "").replace(/^.+,/, "");
                    resolve();
                };

                reader.onerror = function (error) {
                    reject(error);
                };

                if (fileInput.files[0]) {
                    reader.readAsDataURL(fileInput.files[0]);
                } else {
                    reject(new Error('No file selected'));
                }
            });
        }

        document.getElementById('fileInput').addEventListener('change', function () {
            loadImage(this)
                .then(() => {
                    console.log(".");
                })
                .catch(error => {
                    console.error("Es gab einen Fehler beim Laden des Bildes:", error);
                });
        }, false);


        //to attache image
        document.getElementById('fileInput').addEventListener('change', function () {
            const fileName = this.files[0] ? this.files[0].name : 'Keine Datei ausgewählt';
            document.getElementById('selectedFileName').textContent = fileName;
        });

        //really an image?
        const fileInput = document.getElementById('fileInput');
        fileInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (!file.type.startsWith('image/')) {
                alert('Bitte laden Sie nur Bilddateien hoch.');
                event.target.value = '';
            }
        });

        //not bigger than 30 mB
        function checkFileSize(input) {
            if (input.files && input.files[0]) {
                const fileSize = input.files[0].size / 1024 / 1024;
                if (fileSize > 30) {
                    alert("Die Bildgröße darf nicht größer als 30 MB sein.");
                    input.value = "";
                }
            }
        }

        function showLoadingIndicator() {
            document.getElementById('loadingIndicator').style.display = 'flex';
        }

        function hideLoadingIndicator() {
            document.getElementById('loadingIndicator').style.display = 'none';
        }

    </script>
</body>

</html>
