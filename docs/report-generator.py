import pandas as pd

def analyze_transactions(file_path):
    # Load the CSV file
    try:
        df = pd.read_csv(file_path)
    except FileNotFoundError:
        print(f"Error: Could not find the file at {file_path}")
        return

    # Convert 'created_at' to datetime objects
    df['created_at'] = pd.to_datetime(df['created_at'])
    
    # 1. Count Wallet Users since April 18th
    start_date = '2026-04-18'
    wallet_users = df[(df['payment_channel'] == 'wallet') & (df['created_at'] >= start_date)]
    unique_wallet_users = wallet_users['user_id'].nunique()
    
    print(f"--- WALLET USAGE ---")
    print(f"Number of unique people who used the Wallet since {start_date}: {unique_wallet_users}\n")
    
    # 2. Minus charges from amount paid (Net Price Calculation)
    df['net_amount'] = df['amount'] - df['charge']
    
    # 3. Analyze common prices and Gateway vs Wallet correlation
    print(f"--- PRICE & CHANNEL CORRELATION ---")
    
    # Group by the Net Amount (The base price of the item) and Payment Channel
    summary = df.groupby(['net_amount', 'payment_channel']).agg(
        total_transactions=('id', 'count'),
        avg_student_pays=('amount', 'mean'),
        avg_charge=('charge', 'mean'),
        avg_nivasity_profit=('profit', 'mean')
    ).reset_index()

    # Format the output for readability
    for index, row in summary.iterrows():
        base_price = row['net_amount']
        channel = row['payment_channel'].upper()
        count = row['total_transactions']
        student_pays = row['avg_student_pays']
        nivasity_profit = row['avg_nivasity_profit']
        
        print(f"Base Product Price: ₦{base_price:.2f} | Channel: {channel}")
        print(f"  -> Total Purchases: {count}")
        print(f"  -> Student Pays: ₦{student_pays:.2f}")
        print(f"  -> Nivasity Profit: ₦{nivasity_profit:.2f}")
        print("-" * 40)

def check_all_channels(file_path):
    try:
        df = pd.read_csv(file_path)
    except FileNotFoundError:
        print(f"Error: Could not find the file at {file_path}")
        return
        
    print("Scanning transactions...\n")
    
    # Group by payment channel and count unique users
    channel_users = df.groupby('payment_channel')['user_id'].nunique().reset_index()
    channel_users.columns = ['Payment Channel', 'Unique Users']
    
    print("--- UNIQUE USERS BY PAYMENT CHANNEL ---")
    for index, row in channel_users.iterrows():
        print(f"{str(row['Payment Channel']).upper()}: {row['Unique Users']} distinct people")
        
    # Get total transactions per channel for additional context
    channel_tx = df['payment_channel'].value_counts().reset_index()
    channel_tx.columns = ['Payment Channel', 'Total Transactions']
    
    print("\n--- TOTAL TRANSACTIONS BY CHANNEL ---")
    for index, row in channel_tx.iterrows():
        print(f"{str(row['Payment Channel']).upper()}: {row['Total Transactions']} transactions")

if __name__ == "__main__":
    # Change the filename if your CSV is named differently in your local folder
    file_name = "transactions (4).csv"
    print("Starting Nivasity Transaction Analysis...\n")
    analyze_transactions(file_name)
    
    print("Checking all payment channels...\n")
    check_all_channels(file_name)