﻿/*
 * Copyright (c) Open Metaverse Foundation
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

using System;
using System.Collections.Generic;
using System.ComponentModel.Composition;
using Nini.Config;
using OpenMetaverse;
using OpenMetaverse.StructuredData;
using log4net;

namespace Simian.Connectors.Standalone
{
    [ApplicationModule("StandaloneUserClient")]
    public class StandaloneUserClient : IUserClient, IApplicationModule
    {
        private static readonly ILog m_log = LogManager.GetLogger(System.Reflection.MethodBase.GetCurrentMethod().DeclaringType.Name);

        private UUID m_defaultHomeLocation;
        private Vector3d m_defaultHomePosition;
        private Vector3 m_defaultHomeLookAt;

        private FileDataStore m_fileDataStore;
        private Dictionary<string, Identity> m_identities = new Dictionary<string, Identity>();
        private Dictionary<UUID, User> m_users = new Dictionary<UUID, User>();
        private object m_syncRoot = new object();

        public bool Start(Simian simian)
        {
            IConfig config = simian.Config.Configs["Avatars"];

            if (config != null)
            {
                UUID.TryParse(config.GetString("DefaultHomeLocation"), out m_defaultHomeLocation);
                Vector3d.TryParse(config.GetString("DefaultHomePosition"), out m_defaultHomePosition);
                Vector3.TryParse(config.GetString("DefaultHomeLookAt"), out m_defaultHomeLookAt);                
            }

            m_fileDataStore = simian.GetAppModule<FileDataStore>();
            if (m_fileDataStore != null)
            {
                IList<SerializedData> users = m_fileDataStore.Deserialize(UUID.Zero, "Users");
                for (int i = 0; i < users.Count; i++)
                {
                    string name = users[i].Name;
                    UUID ownerID;

                    OSDMap map = null;
                    try { map = OSDParser.Deserialize(users[i].Data) as OSDMap; }
                    catch (Exception) { }

                    if (name == "identities")
                    {
                        if (map != null)
                            DeserializeIdentities(map);
                        else
                            m_log.Warn("Failed to deserialize user identity file");
                    }
                    else if (UUID.TryParse(name, out ownerID))
                    {
                        if (map != null)
                            DeserializeUser(map);
                        else
                            m_log.Warn("Failed to deserialize user file " + name);
                    }
                }
            }

            return true;
        }

        public void Stop()
        {
        }

        #region IUserClient

        public bool CreateUser(string name, byte accessLevel, OSDMap extradata, out User user)
        {
            return CreateUser(new UUID(Utils.MD5String(name)), name, accessLevel, m_defaultHomeLocation, m_defaultHomePosition, m_defaultHomeLookAt, extradata, out user);
        }

        public bool CreateUser(UUID id, string name, byte accessLevel, UUID homeLocation, Vector3d homePosition, Vector3 homeLookAt, OSDMap extraData, out User user)
        {
            // TODO: If we wanted to restrict duplicate names on a grid we could, by storing a HashSet<string> of names
            user = new User
            {
                ID = id,
                Name = name,
                HomeLocation = homeLocation,
                HomePosition = homePosition,
                HomeLookAt = homeLookAt,
                AccessLevel = accessLevel
            };

            lock (m_syncRoot)
            {
                m_users[user.ID] = user;
                SerializeUser(user);
            }

            m_log.Info("Created user " + name);

            return true;
        }

        public bool UpdateUser(User user)
        {
            lock (m_syncRoot)
            {
                if (!m_users.ContainsKey(user.ID))
                    return false;

                m_users[user.ID] = user;
                SerializeUser(user);

                return true;
            }
        }

        public bool UpdateUserField(UUID userID, string field, OSD value)
        {
            lock (m_syncRoot)
            {
                User user;
                if (m_users.TryGetValue(userID, out user))
                {
                    user.SetField(field, value);
                    SerializeUser(user);

                    return true;
                }

                return false;
            }
        }

        public bool TryGetUser(UUID userID, out User user)
        {
            lock (m_syncRoot)
                return m_users.TryGetValue(userID, out user);
        }

        public User[] SearchUsers(string query)
        {
            List<User> users = new List<User>();

            lock (m_syncRoot)
            {
                foreach (User user in m_users.Values)
                {
                    if (user.Name.ToLowerInvariant().Contains(query.ToLowerInvariant()))
                        users.Add(user);
                }
            }

            return users.ToArray();
        }

        public bool TryAuthorizeIdentity(string identifier, string type, string credential, out User user)
        {
            Identity identity;
            user = null;

            lock (m_syncRoot)
            {
                if (m_identities.TryGetValue(identifier, out identity))
                {
                    // Credential check
                    if (credential != identity.Credential)
                        return false;

                    // Resolve this identity to a user
                    if (m_users.TryGetValue(identity.UserID, out user))
                    {
                        return true;
                    }
                    else
                    {
                        // Identity points to a missing user, not good
                        m_log.Error("Identity " + identity + " points to a missing user. Removing user " + identity.UserID);
                        m_identities.Remove(identifier);
                        SerializeIdentities();
                    }
                }
            }

            return false;
        }

        public bool CreateIdentity(Identity identity)
        {
            lock (m_syncRoot)
            {
                if (!m_identities.ContainsKey(identity.Identifier))
                {
                    m_identities.Add(identity.Identifier, identity);
                    SerializeIdentities();

                    m_log.Info("Created identity \"" + identity.Identifier + "\" for user account " + identity.UserID);
                    return true;
                }

                return false;
            }
        }

        public bool DeleteIdentity(string identity)
        {
            // TODO: Implement this
            return false;
        }

        public Identity[] GetUserIdentities(UUID userID)
        {
            // TODO: Implement this
            return null;
        }

        #endregion IUserClient

        #region Serialization/Deserialization

        private void SerializeUser(User user)
        {
            if (m_fileDataStore != null)
            {
                SerializedData data = new SerializedData
                {
                    StoreID = UUID.Zero,
                    Section = "Users",
                    ContentType = "application/llsd+json",
                    Name = user.ID.ToString(),
                    Data = System.Text.Encoding.UTF8.GetBytes(OSDParser.SerializeJsonString(user.GetOSD())),
                    Version = 1
                };

                m_fileDataStore.BeginSerialize(data);
            }
        }

        private void SerializeIdentities()
        {
            if (m_fileDataStore != null)
            {
                OSDMap map = new OSDMap(m_identities.Count);

                foreach (KeyValuePair<string, Identity> kvp in m_identities)
                    map[kvp.Key] = SerializeIdentity(kvp.Value);

                SerializedData data = new SerializedData
                {
                    StoreID = UUID.Zero,
                    Section = "Users",
                    ContentType = "application/llsd+json",
                    Name = "identities",
                    Data = System.Text.Encoding.UTF8.GetBytes(OSDParser.SerializeJsonString(map)),
                    Version = 1
                };

                m_fileDataStore.BeginSerialize(data);
            }
        }

        private OSDMap SerializeIdentity(Identity identity)
        {
            return new OSDMap
            {
                { "credential", OSD.FromString(identity.Credential) },
                { "enabled", OSD.FromBoolean(identity.Enabled) },
                { "identifier", OSD.FromString(identity.Identifier) },
                { "type", OSD.FromString(identity.Type) },
                { "user_id", OSD.FromUUID(identity.UserID) }
            };
        }

        private void DeserializeUser(OSDMap map)
        {
            User user = User.FromOSD(map);
            m_users[user.ID] = user;
        }

        private void DeserializeIdentities(OSDMap map)
        {
            foreach (KeyValuePair<string, OSD> kvp in map)
            {
                OSDMap identityMap = kvp.Value as OSDMap;

                if (identityMap != null)
                {
                    Identity identity = DeserializeIdentity(identityMap);
                    m_identities[identity.Identifier] = identity;
                }
            }
        }

        private Identity DeserializeIdentity(OSDMap map)
        {
            return new Identity
            {
                Credential = map["credential"].AsString(),
                Enabled = map["enabled"].AsBoolean(),
                Identifier = map["identifier"].AsString(),
                Type = map["type"].AsString(),
                UserID = map["user_id"].AsUUID()
            };
        }

        #endregion Serialization/Deserialization

        private static int CharCount(char c, string s)
        {
            int count = 0;

            for (int i = 0; i < s.Length; i++)
            {
                if (s[i] == c)
                    ++count;
            }

            return count;
        }
    }
}
