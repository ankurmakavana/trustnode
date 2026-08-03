FROM node:20-alpine

# Set working directory
WORKDIR /app

# Copy package definitions
COPY package*.json ./

# Install dependencies at build-time
RUN npm install

# Expose Vite HMR port
EXPOSE 5173

# Run package installation update and start Vite in dev mode
CMD ["sh", "-c", "npm install && npm run dev -- --host"]
