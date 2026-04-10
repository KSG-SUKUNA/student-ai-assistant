from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload
import json
import os

SCOPES = ['https://www.googleapis.com/auth/drive']

def get_drive_service():
    creds_str = os.environ.get("GOOGLE_CREDS")

    if not creds_str:
        raise Exception("GOOGLE_CREDS ENV NOT SET ❌")

    creds_json = json.loads(creds_str)

    creds = service_account.Credentials.from_service_account_info(
        creds_json,
        scopes=SCOPES
    )

    return build('drive', 'v3', credentials=creds)


def upload_file(file_path, filename):
    service = get_drive_service()

    file_metadata = {
        'name': filename,
        'parents': ['YOUR_FOLDER_ID']  # 🔥 PUT YOUR FOLDER ID HERE
    }

    media = MediaFileUpload(file_path, resumable=True)

    file = service.files().create(
        body=file_metadata,
        media_body=media,
        fields='id'
    ).execute()

    file_id = file.get('id')

    return f"https://drive.google.com/file/d/{file_id}/view"