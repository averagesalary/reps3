from flask import Flask, request, Response
import requests

app = Flask(__name__)

@app.route('/proxy')
def proxy():
    """
    Simple proxy that takes 'url' and 'cookies' as query parameters.
    Example: /proxy?url=https://example.com/secure&cookies=session_id=abc123;user_token=def456
    """
    # Get parameters from the request
    target_url = request.args.get('url')
    cookies_str = request.args.get('cookies', '')  # Optional, defaults to empty

    if not target_url:
        return Response("Error: 'url' parameter is required", status=400, mimetype='text/plain')

    # Parse the cookies string into a dictionary
    # Format: "name1=value1; name2=value2"
    cookies_dict = {}
    if cookies_str:
        for cookie in cookies_str.split(';'):
            cookie = cookie.strip()
            if '=' in cookie:
                name, value = cookie.split('=', 1)
                cookies_dict[name.strip()] = value.strip()

    try:
        # Make the request to the target URL with the specified cookies
        response = requests.get(target_url, cookies=cookies_dict, timeout=30)
        
        # Return the response content, status code, and content-type
        return Response(
            response.content,
            status=response.status_code,
            content_type=response.headers.get('Content-Type', 'application/octet-stream')
        )
        
    except requests.exceptions.RequestException as e:
        return Response(f"Error fetching URL: {str(e)}", status=500, mimetype='text/plain')

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)