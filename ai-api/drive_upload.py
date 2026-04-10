from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload
import json
import os

# 🔥 LOAD FROM ENV (Render safe)
creds_json = json.loads(os.environ["GOOGLE_CREDS"])

SCOPES = ['https://www.googleapis.com/auth/drive']

creds = service_account.Credentials.from_service_account_info(
    creds_json,
    scopes=SCOPES
)

service = build('drive', 'v3', credentials=creds)


def upload_file(file_path, filename):
    file_metadata = {
        'name': filename,
        'parents': ['YOUR_FOLDER_ID']  # 🔥 PUT YOUR REAL FOLDER ID
    }

    media = MediaFileUpload(file_path, resumable=True)

    file = service.files().create(
        body=file_metadata,
        media_body=media,
        fields='id'
    ).execute()

    file_id = file.get('id')

    return f"https://drive.google.com/file/d/{file_id}/view"