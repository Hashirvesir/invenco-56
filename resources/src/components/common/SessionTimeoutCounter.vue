<template>
  <div
    v-if="timeoutMinutes && remainingSeconds !== null"
    class="session-timeout-badge"
    :class="{ 'session-timeout-warning': remainingSeconds <= 60 }"
    :title="$t('Session_Time_Remaining') || 'Time remaining on this login session'"
  >
    <lucide-icon name="timer" />
    <span class="session-timeout-time">{{ formattedTime }}</span>
  </div>
</template>

<script>
import { mapGetters, mapActions } from "vuex";

const ACTIVITY_EVENTS = ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"];
const ACTIVITY_THROTTLE_MS = 1000;

export default {
  name: "SessionTimeoutCounter",

  data() {
    return {
      lastActivityAt: Date.now(),
      remainingSeconds: null,
      intervalId: null,
      loggedOutTriggered: false,
    };
  },

  computed: {
    ...mapGetters(["getSessionLifetimeMinutes"]),

    timeoutMinutes() {
      const n = Number(this.getSessionLifetimeMinutes);
      return !isNaN(n) && n > 0 ? n : null;
    },

    formattedTime() {
      if (this.remainingSeconds === null) return "";
      const total = Math.max(0, this.remainingSeconds);

      const days = Math.floor(total / 86400);
      const hours = Math.floor((total % 86400) / 3600);
      const minutes = Math.floor((total % 3600) / 60);
      const seconds = total % 60;

      if (days > 0) return `${days}d ${hours}h`;
      if (hours > 0) return `${hours}h ${minutes}m`;
      return `${minutes}:${String(seconds).padStart(2, "0")}`;
    },
  },

  watch: {
    timeoutMinutes() {
      this.resetActivity();
      this.tick();
    },
  },

  methods: {
    ...mapActions(["logout"]),

    resetActivity() {
      this.lastActivityAt = Date.now();
      this.loggedOutTriggered = false;
    },

    // Only bump the activity timestamp at most once per second, so a stream
    // of mousemove events doesn't flood us with timestamp writes.
    throttledReset() {
      const now = Date.now();
      if (now - this.lastActivityAt > ACTIVITY_THROTTLE_MS) {
        this.lastActivityAt = now;
      }
    },

    tick() {
      if (!this.timeoutMinutes) {
        this.remainingSeconds = null;
        return;
      }

      const remainingMs = this.timeoutMinutes * 60 * 1000 - (Date.now() - this.lastActivityAt);
      this.remainingSeconds = Math.max(0, Math.ceil(remainingMs / 1000));

      if (remainingMs <= 0 && !this.loggedOutTriggered) {
        this.loggedOutTriggered = true;
        this.logout();
      }
    },
  },

  mounted() {
    ACTIVITY_EVENTS.forEach(evt => {
      window.addEventListener(evt, this.throttledReset, { passive: true });
    });
    this.tick();
    this.intervalId = setInterval(this.tick, 1000);
  },

  beforeDestroy() {
    ACTIVITY_EVENTS.forEach(evt => {
      window.removeEventListener(evt, this.throttledReset);
    });
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  },
};
</script>

<style scoped>
.session-timeout-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 12px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #6b7280;
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  transition: all 0.3s;
}

.session-timeout-warning {
  border-color: #f87171;
  color: #dc2626;
  background: #fef2f2;
  animation: session-timeout-pulse 1s infinite;
}

@keyframes session-timeout-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

body.dark-theme .session-timeout-badge {
  background: #1a1a2e;
  border-color: #2d2d44;
  color: #d0d0d0;
}

body.dark-theme .session-timeout-warning {
  border-color: #dc2626;
  color: #f87171;
  background: rgba(220, 38, 38, 0.12);
}
</style>
